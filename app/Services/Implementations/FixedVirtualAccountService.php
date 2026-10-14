<?php

namespace App\Services\Implementations;

use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\Student;
use App\Models\VirtualAccount;
use App\Services\Contracts\FixedVirtualAccountServiceInterface;
use App\Services\MandiriH2hClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class FixedVirtualAccountService implements FixedVirtualAccountServiceInterface
{
    private const ENDPOINT = '/api/internal/va/create';

    public function __construct(private readonly MandiriH2hClient $mandiriH2hClient) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return VirtualAccount::query()
            ->with(['branch:id,name,code', 'student:id,branch_id,nis,name,status'])
            ->where('va_type', 'fixed')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function activeBranches(): Collection
    {
        return Branch::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'va_fixed_prefix']);
    }

    public function create(array $data): array
    {
        if (! $this->mandiriH2hClient->isConfigured()) {
            Log::error('Cannot create fixed VA because Mandiri H2H configuration is incomplete.');

            throw new ServiceUnavailableHttpException(null, 'Integrasi Mandiri H2H belum dikonfigurasi. Hubungi administrator sistem.');
        }

        $branch = Branch::query()->active()->findOrFail($data['branch_id']);
        $vaNumber = $data['va_number_mode'] === 'automatic'
            ? $branch->va_fixed_prefix.$data['nis']
            : $data['manual_va_number'];
        $expiredAt = str_replace('T', ' ', $data['expired_at']).':00';

        [$student, $virtualAccount] = $this->storeLocalRecords($branch, $data, $vaNumber, $expiredAt);

        $payload = [
            'partnerServiceId' => (string) config('services.mandiri_h2h.partner_service_id'),
            'customerNo' => $vaNumber,
            'virtualAccountNo' => $vaNumber,
            'virtualAccountName' => $student->name,
            'totalAmount' => '0.00',
            'feeAmount' => '0.00',
            'billCode' => 'FIXED-'.$branch->code.'-'.$student->nis,
            'billName' => 'Fixed VA SPP - '.$student->name,
            'expiredAt' => $expiredAt,
        ];
        $startedAt = hrtime(true);

        try {
            $response = $this->mandiriH2hClient->createVirtualAccount($payload);
        } catch (ConnectionException $exception) {
            $virtualAccount->update(['status' => 'verification_required']);

            H2hApiLog::query()->create([
                'branch_id' => $branch->id,
                'virtual_account_id' => $virtualAccount->id,
                'endpoint' => self::ENDPOINT,
                'http_method' => 'POST',
                'request_payload' => $payload,
                'duration_ms' => $this->elapsedMs($startedAt),
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);

            Log::warning('Mandiri H2H connection outcome is unknown for fixed VA creation.', [
                'branch_id' => $branch->id,
                'virtual_account_id' => $virtualAccount->id,
            ]);

            return ['outcome' => self::OUTCOME_UNKNOWN, 'virtualAccount' => $virtualAccount];
        }

        $responsePayload = $response->json();
        if (! is_array($responsePayload)) {
            $responsePayload = ['body' => Str::limit($response->body(), 5000)];
        }

        H2hApiLog::query()->create([
            'branch_id' => $branch->id,
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => self::ENDPOINT,
            'http_method' => 'POST',
            'response_status' => $response->status(),
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        if (! $response->successful()) {
            $virtualAccount->update([
                'status' => $response->serverError() ? 'verification_required' : 'failed',
                'h2h_response_raw' => $responsePayload,
            ]);

            return [
                'outcome' => $response->serverError() ? self::OUTCOME_SERVER_ERROR : self::OUTCOME_REJECTED,
                'virtualAccount' => $virtualAccount,
            ];
        }

        $virtualAccount->update([
            'status' => 'active',
            'h2h_response_raw' => $responsePayload,
        ]);
        $student->update(['status' => 'active']);

        return ['outcome' => self::OUTCOME_ACTIVE, 'virtualAccount' => $virtualAccount];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Student, 1: VirtualAccount}
     */
    private function storeLocalRecords(Branch $branch, array $data, string $vaNumber, string $expiredAt): array
    {
        return DB::transaction(function () use ($branch, $data, $vaNumber, $expiredAt): array {
            $student = Student::query()->create([
                'branch_id' => $branch->id,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'name' => $data['name'],
                'grade_class' => $data['grade_class'] ?? null,
                'parent_name' => $data['parent_name'] ?? null,
                'parent_email' => $data['parent_email'],
                'additional_email' => $data['additional_email'] ?? null,
                'parent_phone' => $data['parent_phone'] ?? null,
                'fixed_va_number' => $vaNumber,
                'status' => 'pending_h2h',
            ]);

            $virtualAccount = VirtualAccount::query()->create([
                'branch_id' => $branch->id,
                'student_id' => $student->id,
                'va_type' => 'fixed',
                'partner_service_id' => (string) config('services.mandiri_h2h.partner_service_id'),
                'customer_no' => $vaNumber,
                'virtual_account_no' => $vaNumber,
                'virtual_account_name' => $student->name,
                'current_amount' => '0.00',
                'expired_at' => $expiredAt,
                'status' => 'pending_h2h',
            ]);

            return [$student, $virtualAccount];
        });
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
