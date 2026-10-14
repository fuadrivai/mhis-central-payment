<?php

namespace App\Services\Implementations;

use App\Jobs\SendBranchWebhookDelivery;
use App\Models\BranchWebhookDelivery;
use App\Models\PaymentTransaction;
use App\Services\Contracts\BranchWebhookDeliveryServiceInterface;

class BranchWebhookDeliveryService implements BranchWebhookDeliveryServiceInterface
{
    public function createForPaymentTransaction(PaymentTransaction $paymentTransaction): BranchWebhookDelivery
    {
        $delivery = $this->findOrCreate($paymentTransaction);

        if ($delivery->wasRecentlyCreated && $delivery->status === 'pending') {
            SendBranchWebhookDelivery::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    public function retry(PaymentTransaction $paymentTransaction): BranchWebhookDelivery
    {
        $delivery = $this->findOrCreate($paymentTransaction);
        $webhookUrl = $paymentTransaction->branch()->value('webhook_url');

        if ($webhookUrl === null) {
            $delivery->update([
                'webhook_url' => null,
                'status' => 'skipped',
                'last_error' => null,
                'response_status' => null,
                'response_body' => null,
                'delivered_at' => null,
            ]);

            return $delivery->refresh();
        }

        $delivery->update([
            'webhook_url' => $webhookUrl,
            'status' => 'pending',
            'last_error' => null,
            'response_status' => null,
            'response_body' => null,
            'last_attempt_at' => null,
            'delivered_at' => null,
        ]);

        SendBranchWebhookDelivery::dispatch($delivery->id)->afterCommit();

        return $delivery->refresh();
    }

    private function findOrCreate(PaymentTransaction $paymentTransaction): BranchWebhookDelivery
    {
        $paymentTransaction->loadMissing([
            'bank:id,code',
            'branch:id,name,webhook_url',
            'invoice:id,bill_code,bill_name',
            'virtualAccount:id,virtual_account_no',
        ]);

        $webhookUrl = $paymentTransaction->branch?->webhook_url;
        $delivery = $paymentTransaction->webhookDelivery()->firstOrCreate([], [
            'branch_id' => $paymentTransaction->branch_id,
            'webhook_url' => $webhookUrl,
            'status' => $webhookUrl === null ? 'skipped' : 'pending',
            'attempts' => 0,
            'payload' => $this->payload($paymentTransaction),
        ]);

        return $delivery;
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function payload(PaymentTransaction $paymentTransaction): array
    {
        $bankCode = strtoupper((string) $paymentTransaction->bank?->code);

        return [
            'id' => 'MHIS-'.$paymentTransaction->id,
            'amount' => $this->amountValue((string) $paymentTransaction->paid_amount),
            'status' => 'PAID',
            'created' => $paymentTransaction->created_at?->toISOString(),
            'paid_at' => $paymentTransaction->paid_at?->toISOString(),
            'updated' => $paymentTransaction->updated_at?->toISOString(),
            'currency' => 'IDR',
            'bank_code' => $bankCode,
            'payment_id' => $paymentTransaction->payment_request_id,
            'description' => $paymentTransaction->invoice?->bill_name,
            'external_id' => $paymentTransaction->invoice?->bill_code,
            'paid_amount' => $this->amountValue((string) $paymentTransaction->paid_amount),
            'merchant_name' => config('app.name'),
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => strtoupper((string) ($paymentTransaction->channel ?: $bankCode)),
            'payment_destination' => $paymentTransaction->virtualAccount?->virtual_account_no,
        ];
    }

    private function amountValue(string $amount): int|float
    {
        $numericAmount = (float) $amount;

        return $numericAmount === (float) (int) $numericAmount
            ? (int) $numericAmount
            : $numericAmount;
    }
}
