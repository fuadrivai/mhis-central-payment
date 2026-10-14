<?php

namespace App\Jobs;

use App\Models\BranchWebhookDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendBranchWebhookDelivery implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 1800];

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(): void
    {
        $delivery = DB::transaction(function (): ?BranchWebhookDelivery {
            $delivery = BranchWebhookDelivery::query()
                ->lockForUpdate()
                ->findOrFail($this->deliveryId);

            if (in_array($delivery->status, ['delivered', 'failed', 'skipped'], true)) {
                return null;
            }

            if ($delivery->webhook_url === null) {
                $delivery->update(['status' => 'skipped']);

                return null;
            }

            $delivery->update([
                'status' => 'processing',
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
                'response_status' => null,
                'response_body' => null,
            ]);

            return $delivery;
        });

        if ($delivery === null) {
            return;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout($this->timeout)
                ->withHeaders(['X-Delivery-ID' => (string) $delivery->id])
                ->post($delivery->webhook_url, $delivery->payload);
        } catch (ConnectionException $exception) {
            $delivery->update([
                'status' => 'pending',
                'last_error' => 'Connection failure ('.class_basename($exception).').',
            ]);

            throw $exception;
        }

        $responseBody = Str::limit($response->body(), 4000);
        $responseStatus = $response->status();

        if ($response->successful()) {
            $delivery->update([
                'status' => 'delivered',
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'last_error' => null,
                'delivered_at' => now(),
            ]);

            return;
        }

        if ($responseStatus === 429 || $response->serverError()) {
            $delivery->update([
                'status' => 'pending',
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'last_error' => 'Endpoint returned a retryable HTTP '.$responseStatus.'.',
            ]);

            throw new RuntimeException('Branch webhook returned a retryable HTTP status.');
        }

        $delivery->update([
            'status' => 'failed',
            'response_status' => $responseStatus,
            'response_body' => $responseBody,
            'last_error' => 'Endpoint returned a non-retryable HTTP '.$responseStatus.'.',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        BranchWebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->whereNotIn('status', ['delivered', 'skipped'])
            ->update([
                'status' => 'failed',
                'last_error' => 'Delivery exhausted its queue retries'
                    .($exception === null ? '.' : ' ('.class_basename($exception).').'),
            ]);
    }
}
