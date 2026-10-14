<?php

namespace App\Services\Implementations;

use App\Enums\VirtualAccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\VirtualAccount;
use App\Services\Contracts\BankGatewayInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use App\Services\Gateways\BankGatewayManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class VirtualAccountService implements VirtualAccountServiceInterface
{
    private const VA_NUMBER_LENGTH = 16;

    private const MAX_NUMBER_ATTEMPTS = 10;

    public function __construct(private readonly BankGatewayManager $gateways) {}

    public function paginate(?VirtualAccountType $type = null, int $perPage = 15): LengthAwarePaginator
    {
        return VirtualAccount::query()
            ->with([
                'branch:id,name,code',
                'bank:id,name,code',
                'student:id,branch_id,nis,name,status',
            ])
            ->when(
                $type === VirtualAccountType::Dynamic,
                fn ($query) => $query->with('invoice:id,invoice_number,bill_code,bill_name,total_amount,status,paid_at,expired_at'),
            )
            ->when($type, fn ($query) => $query->where('va_type', $type->value))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function activeBranches(): Collection
    {
        return Branch::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'va_fixed_prefix']);
    }

    public function activeBanks(): Collection
    {
        return Bank::query()->active()->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function createFixed(array $data): array
    {
        [$bank, $gateway] = $this->resolveBank((int) $data['bank_id']);

        $branch = Branch::query()->active()->findOrFail($data['branch_id']);
        $existingStudent = isset($data['student_id'])
            ? Student::query()->where('branch_id', $branch->id)->findOrFail($data['student_id'])
            : null;
        $nis = $existingStudent->nis ?? $data['nis'];
        $vaNumber = $data['va_number_mode'] === 'automatic'
            ? $branch->va_fixed_prefix.$nis
            : $data['manual_va_number'];
        $expiredAt = str_replace('T', ' ', $data['expired_at']).':00';

        [$student, $virtualAccount] = DB::transaction(function () use ($bank, $gateway, $branch, $data, $existingStudent, $vaNumber, $expiredAt): array {
            $student = $existingStudent ?? Student::query()->create([
                'branch_id' => $branch->id,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'name' => $data['name'],
                'grade_class' => $data['grade_class'] ?? null,
                'parent_name' => $data['parent_name'] ?? null,
                'parent_email' => $data['parent_email'],
                'additional_email' => $data['additional_email'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'status' => 'pending_h2h',
            ]);

            $virtualAccount = VirtualAccount::query()->create([
                'branch_id' => $branch->id,
                'bank_id' => $bank->id,
                'student_id' => $student->id,
                'va_type' => VirtualAccountType::Fixed->value,
                'partner_service_id' => $gateway->partnerServiceId(),
                'customer_no' => $vaNumber,
                'virtual_account_no' => $vaNumber,
                'virtual_account_name' => $student->name,
                'current_amount' => '0.00',
                'expired_at' => $expiredAt,
                'status' => 'pending_h2h',
            ]);

            return [$student, $virtualAccount];
        });

        $outcome = $this->registerWithBank($virtualAccount, $gateway, [
            'partnerServiceId' => $gateway->partnerServiceId(),
            'customerNo' => $vaNumber,
            'virtualAccountNo' => $vaNumber,
            'virtualAccountName' => $student->name,
            'totalAmount' => '0.00',
            'feeAmount' => '0.00',
            'billCode' => 'FIXED-'.$branch->code.'-'.$student->nis,
            'billName' => 'Fixed VA SPP - '.$student->name,
            'expiredAt' => $expiredAt,
        ]);

        if ($outcome === self::OUTCOME_ACTIVE && $student->status === 'pending_h2h') {
            $student->update(['status' => 'active']);
        }

        return ['outcome' => $outcome, 'virtualAccount' => $virtualAccount];
    }

    public function createDynamic(Invoice $invoice, int $bankId): array
    {
        [$bank, $gateway] = $this->resolveBank($bankId);

        if ($invoice->status !== 'pending') {
            throw ValidationException::withMessages(['invoice' => 'Hanya invoice berstatus pending yang dapat dibuatkan VA.']);
        }

        if ($invoice->expired_at === null || $invoice->expired_at->isPast()) {
            throw ValidationException::withMessages(['invoice' => 'Invoice harus memiliki tanggal kedaluwarsa di masa mendatang.']);
        }

        if (VirtualAccount::query()
            ->where('invoice_id', $invoice->id)
            ->where('bank_id', $bank->id)
            ->where('va_type', VirtualAccountType::Dynamic->value)
            ->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Invoice ini sudah memiliki Dynamic VA pada bank tersebut.']);
        }

        $branch = $invoice->branch;
        $totalAmount = number_format((float) $invoice->total_amount, 2, '.', '');

        $virtualAccount = $this->storeDynamicRecord($branch, $bank, $gateway, $invoice, $totalAmount);
        $vaNumber = $virtualAccount->virtual_account_no;

        $outcome = $this->registerWithBank($virtualAccount, $gateway, [
            'partnerServiceId' => $gateway->partnerServiceId(),
            'customerNo' => $vaNumber,
            'virtualAccountNo' => $vaNumber,
            'virtualAccountName' => $invoice->customer_name,
            'totalAmount' => $totalAmount,
            'feeAmount' => number_format((float) $invoice->fee_amount, 2, '.', ''),
            'billCode' => $invoice->bill_code,
            'billName' => $invoice->bill_name,
            'expiredAt' => $invoice->expired_at->format('Y-m-d H:i:s'),
        ], $invoice);

        return ['outcome' => $outcome, 'virtualAccount' => $virtualAccount];
    }

    public function voidInvoice(Invoice $invoice): string
    {
        [$invoice, $virtualAccount, $bank, $gateway] = DB::transaction(function () use ($invoice): array {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $virtualAccount = VirtualAccount::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->lockForUpdate()
                ->first();

            if (
                $lockedInvoice->status !== 'pending'
                || $lockedInvoice->expired_at === null
                || $lockedInvoice->expired_at->isPast()
                || $virtualAccount === null
                || $virtualAccount->va_type !== VirtualAccountType::Dynamic->value
                || $virtualAccount->status !== 'active'
            ) {
                throw ValidationException::withMessages([
                    'invoice' => 'Hanya invoice pending dengan Dynamic VA aktif yang dapat di-void.',
                ]);
            }

            [$bank, $gateway] = $this->resolveBank((int) $virtualAccount->bank_id);
            $virtualAccount->update(['status' => 'verification_required']);

            return [$lockedInvoice, $virtualAccount, $bank, $gateway];
        });

        return $this->deactivateVirtualAccount($invoice, $virtualAccount, $bank, $gateway, now(), automatic: false);
    }

    public function expireDueVirtualAccount(VirtualAccount $virtualAccount): string
    {
        [$invoice, $virtualAccount, $bank, $gateway] = DB::transaction(function () use ($virtualAccount): array {
            $lockedVirtualAccount = VirtualAccount::query()
                ->lockForUpdate()
                ->findOrFail($virtualAccount->id);

            if (
                $lockedVirtualAccount->expired_at === null
                || $lockedVirtualAccount->expired_at->isFuture()
                || ! in_array($lockedVirtualAccount->status, ['active', 'verification_required'], true)
            ) {
                return [null, $lockedVirtualAccount, null, null];
            }

            $invoice = $lockedVirtualAccount->invoice_id === null
                ? null
                : Invoice::query()->lockForUpdate()->findOrFail($lockedVirtualAccount->invoice_id);

            if ($invoice !== null && ! in_array($invoice->status, ['pending', 'expired'], true)) {
                return [null, $lockedVirtualAccount, null, null];
            }

            [$bank, $gateway] = $this->resolveBank((int) $lockedVirtualAccount->bank_id);
            $lockedVirtualAccount->update(['status' => 'verification_required']);

            return [$invoice, $lockedVirtualAccount, $bank, $gateway];
        });

        if ($bank === null || $gateway === null) {
            return self::OUTCOME_NOT_ELIGIBLE;
        }

        return $this->deactivateVirtualAccount(
            $invoice,
            $virtualAccount,
            $bank,
            $gateway,
            $virtualAccount->expired_at,
            automatic: true,
        );
    }

    private function deactivateVirtualAccount(
        ?Invoice $invoice,
        VirtualAccount $virtualAccount,
        Bank $bank,
        BankGatewayInterface $gateway,
        Carbon $deactivatedAt,
        bool $automatic,
    ): string {
        $payload = [
            'virtualAccountNo' => $virtualAccount->virtual_account_no,
            'totalAmount' => number_format((float) $virtualAccount->current_amount, 2, '.', ''),
            'expiredAt' => $deactivatedAt->format('Y-m-d H:i:s'),
            'status' => 'INACTIVE',
        ];
        $logContext = [
            'branch_id' => $virtualAccount->branch_id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice?->id,
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => $gateway->updateVirtualAccountEndpoint(),
            'http_method' => 'PUT',
            'request_payload' => $payload,
        ];
        $startedAt = hrtime(true);

        try {
            $response = $gateway->updateVirtualAccount($payload);
        } catch (ConnectionException $exception) {
            H2hApiLog::query()->create($logContext + [
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            Log::warning('Bank connection outcome is unknown for virtual account void.', [
                'bank_id' => $bank->id,
                'invoice_id' => $invoice?->id,
                'virtual_account_id' => $virtualAccount->id,
            ]);

            return self::OUTCOME_UNKNOWN;
        }

        H2hApiLog::query()->create($logContext + [
            'response_status' => $response->status,
            'response_payload' => $response->payload,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        if (! $response->successful) {
            $invoiceStateChanged = DB::transaction(function () use ($invoice, $virtualAccount, $response, $automatic): bool {
                $currentInvoice = $invoice === null
                    ? null
                    : Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
                $lockedVirtualAccount = VirtualAccount::query()->lockForUpdate()->findOrFail($virtualAccount->id);
                $invoiceStateChanged = $automatic
                    || ($currentInvoice !== null && (
                        $currentInvoice->status !== 'pending'
                        || $currentInvoice->expired_at?->isPast()
                    ));

                $lockedVirtualAccount->update([
                    'status' => $response->serverError
                        || $invoiceStateChanged
                        ? 'verification_required'
                        : 'active',
                    'h2h_response_raw' => $response->payload,
                ]);

                return $invoiceStateChanged;
            });

            if ($response->serverError) {
                return self::OUTCOME_SERVER_ERROR;
            }

            if ($automatic) {
                return self::OUTCOME_REJECTED;
            }

            return $invoiceStateChanged ? self::OUTCOME_UNKNOWN : self::OUTCOME_REJECTED;
        }

        DB::transaction(function () use ($invoice, $virtualAccount, $deactivatedAt, $response): void {
            $lockedInvoice = $invoice === null
                ? null
                : Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $lockedVirtualAccount = VirtualAccount::query()->lockForUpdate()->findOrFail($virtualAccount->id);

            $lockedVirtualAccount->update([
                'status' => 'inactive',
                'expired_at' => $deactivatedAt,
                'h2h_response_raw' => $response->payload,
            ]);

            if ($lockedInvoice?->status === 'pending') {
                $lockedInvoice->update(['status' => 'expired']);
            }
        });

        return self::OUTCOME_VOIDED;
    }

    /**
     * @return array{0: Bank, 1: BankGatewayInterface}
     */
    private function resolveBank(int $bankId): array
    {
        $bank = Bank::query()->active()->findOrFail($bankId);
        $gateway = $this->gateways->for($bank);

        if (! $gateway->isConfigured()) {
            Log::error('Cannot create virtual account because the bank integration is not fully configured.', [
                'bank' => $bank->code,
            ]);

            throw new ServiceUnavailableHttpException(null, "Integrasi {$bank->name} belum dikonfigurasi. Hubungi administrator sistem.");
        }

        return [$bank, $gateway];
    }

    /**
     * Inserts the dynamic VA with a random number. The number is checked against every existing VA and the
     * database unique index is the final guard, so a concurrent collision simply triggers another attempt.
     */
    private function storeDynamicRecord(
        Branch $branch,
        Bank $bank,
        BankGatewayInterface $gateway,
        Invoice $invoice,
        string $totalAmount,
    ): VirtualAccount {
        for ($attempt = 0; $attempt < self::MAX_NUMBER_ATTEMPTS; $attempt++) {
            $vaNumber = $this->randomDynamicVaNumber($branch);

            if (VirtualAccount::query()->where('virtual_account_no', $vaNumber)->exists()) {
                continue;
            }

            try {
                return DB::transaction(fn (): VirtualAccount => VirtualAccount::query()->create([
                    'branch_id' => $branch->id,
                    'bank_id' => $bank->id,
                    'student_id' => $invoice->student_id,
                    'invoice_id' => $invoice->id,
                    'va_type' => VirtualAccountType::Dynamic->value,
                    'partner_service_id' => $gateway->partnerServiceId(),
                    'customer_no' => $vaNumber,
                    'virtual_account_no' => $vaNumber,
                    'virtual_account_name' => $invoice->customer_name,
                    'current_amount' => $totalAmount,
                    'expired_at' => $invoice->expired_at,
                    'status' => 'pending_h2h',
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        Log::error('Unable to generate a unique dynamic VA number.', ['branch_id' => $branch->id]);

        throw new ServiceUnavailableHttpException(null, 'Gagal membuat nomor VA unik. Silakan coba lagi.');
    }

    /**
     * Dynamic VA number: the branch dynamic prefix followed by random digits, 16 digits in total.
     */
    private function randomDynamicVaNumber(Branch $branch): string
    {
        $suffixLength = self::VA_NUMBER_LENGTH - strlen($branch->va_dynamic_prefix);

        if ($suffixLength < 1) {
            throw ValidationException::withMessages(['invoice' => 'Prefix Dynamic VA cabang terlalu panjang untuk nomor VA 16 digit.']);
        }

        $suffix = '';
        for ($i = 0; $i < $suffixLength; $i++) {
            $suffix .= random_int(0, 9);
        }

        return $branch->va_dynamic_prefix.$suffix;
    }

    /**
     * Sends the VA to the bank, records the exchange and settles the local VA status.
     *
     * @param  array<string, string>  $payload
     * @return self::OUTCOME_*
     */
    private function registerWithBank(
        VirtualAccount $virtualAccount,
        BankGatewayInterface $gateway,
        array $payload,
        ?Invoice $invoice = null,
    ): string {
        $logContext = [
            'branch_id' => $virtualAccount->branch_id,
            'bank_id' => $virtualAccount->bank_id,
            'invoice_id' => $invoice?->id,
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => $gateway->createVirtualAccountEndpoint(),
            'http_method' => 'POST',
            'request_payload' => $payload,
        ];
        $startedAt = hrtime(true);

        try {
            $response = $gateway->createVirtualAccount($payload);
        } catch (ConnectionException $exception) {
            $virtualAccount->update(['status' => 'verification_required']);

            H2hApiLog::query()->create($logContext + [
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            Log::warning('Bank connection outcome is unknown for virtual account creation.', [
                'bank_id' => $virtualAccount->bank_id,
                'virtual_account_id' => $virtualAccount->id,
            ]);

            return self::OUTCOME_UNKNOWN;
        }

        H2hApiLog::query()->create($logContext + [
            'response_status' => $response->status,
            'response_payload' => $response->payload,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        if (! $response->successful) {
            $virtualAccount->update([
                'status' => $response->serverError ? 'verification_required' : 'failed',
                'h2h_response_raw' => $response->payload,
            ]);

            return $response->serverError ? self::OUTCOME_SERVER_ERROR : self::OUTCOME_REJECTED;
        }

        $virtualAccount->update(['status' => 'active', 'h2h_response_raw' => $response->payload]);

        return self::OUTCOME_ACTIVE;
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
