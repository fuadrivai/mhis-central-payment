<?php

namespace App\Services\Implementations;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\VirtualAccount;
use App\Services\Contracts\BranchWebhookDeliveryServiceInterface;
use App\Services\Contracts\PaymentNotificationServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentNotificationService implements PaymentNotificationServiceInterface
{
    public function __construct(
        private readonly BranchWebhookDeliveryServiceInterface $branchWebhookDeliveries,
    ) {}

    public function process(array $payload): string
    {
        try {
            return DB::transaction(function () use ($payload): string {
                $data = $payload['data'];
                $virtualAccount = VirtualAccount::query()
                    ->where('virtual_account_no', $data['virtualAccountNo'])
                    ->lockForUpdate()
                    ->first();

                if ($virtualAccount === null || $virtualAccount->invoice_id === null) {
                    return self::OUTCOME_NOT_FOUND;
                }

                $invoice = Invoice::query()->lockForUpdate()->find($virtualAccount->invoice_id);

                if ($invoice === null) {
                    return self::OUTCOME_NOT_FOUND;
                }

                $duplicate = PaymentTransaction::query()
                    ->where('bank_id', $virtualAccount->bank_id)
                    ->where('payment_reference_no', $data['referenceNo'])
                    ->first();

                if ($duplicate !== null) {
                    return $this->duplicateOutcome($duplicate, $invoice->id, $virtualAccount->id, $data['paidAmount']);
                }

                if ($data['customerNo'] !== $virtualAccount->customer_no
                    || $data['billCode'] !== $invoice->bill_code
                    || $this->normalizeAmount($data['totalAmount']) !== $this->normalizeAmount((string) $invoice->total_amount)
                    || $this->normalizeAmount($data['totalAmount']) !== $this->normalizeAmount((string) $virtualAccount->current_amount)
                    || $this->normalizeAmount($data['paidAmount']) !== $this->normalizeAmount($data['totalAmount'])) {
                    return self::OUTCOME_INVALID;
                }

                $paidAt = Carbon::parse($payload['timestamp'])->utc();

                $paymentTransaction = PaymentTransaction::query()->create([
                    'branch_id' => $invoice->branch_id,
                    'bank_id' => $virtualAccount->bank_id,
                    'invoice_id' => $invoice->id,
                    'virtual_account_id' => $virtualAccount->id,
                    'paid_amount' => $data['paidAmount'],
                    'payment_reference_no' => $data['referenceNo'],
                    'payment_request_id' => $data['paymentRequestId'],
                    'paid_at' => $paidAt,
                    'raw_payload' => $payload,
                ]);

                $this->branchWebhookDeliveries->createForPaymentTransaction($paymentTransaction);

                if ($invoice->status !== 'paid') {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => $paidAt,
                    ]);
                }

                return self::OUTCOME_PROCESSED;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $data = $payload['data'];
            $virtualAccount = VirtualAccount::query()
                ->where('virtual_account_no', $data['virtualAccountNo'])
                ->first();

            if ($virtualAccount === null) {
                throw $exception;
            }

            $duplicate = PaymentTransaction::query()
                ->where('bank_id', $virtualAccount->bank_id)
                ->where('payment_reference_no', $data['referenceNo'])
                ->first();

            if ($duplicate === null) {
                throw $exception;
            }

            return $this->duplicateOutcome(
                $duplicate,
                (int) $virtualAccount->invoice_id,
                $virtualAccount->id,
                $data['paidAmount'],
            );
        }
    }

    private function duplicateOutcome(
        PaymentTransaction $transaction,
        int $invoiceId,
        int $virtualAccountId,
        string $paidAmount,
    ): string {
        return $transaction->invoice_id === $invoiceId
            && $transaction->virtual_account_id === $virtualAccountId
            && $this->normalizeAmount((string) $transaction->paid_amount) === $this->normalizeAmount($paidAmount)
            ? self::OUTCOME_DUPLICATE
            : self::OUTCOME_CONFLICT;
    }

    private function normalizeAmount(string $amount): string
    {
        [$whole, $fraction] = explode('.', $amount, 2);

        return (ltrim($whole, '0') ?: '0').'.'.$fraction;
    }
}
