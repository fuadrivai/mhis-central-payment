<?php

namespace Tests\Feature;

use App\Jobs\SendBranchWebhookDelivery;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\BranchWebhookDelivery;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BranchWebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_callback_creates_one_branch_delivery_with_xendit_compatible_payload(): void
    {
        Queue::fake([SendBranchWebhookDelivery::class]);
        $invoice = $this->createInvoiceWithVirtualAccount('https://branch.example/callback');

        $this->postCallback($this->callbackPayload())->assertOk();

        $transaction = PaymentTransaction::query()->sole();
        $delivery = BranchWebhookDelivery::query()->sole();

        $this->assertSame($transaction->id, $delivery->payment_transaction_id);
        $this->assertSame($invoice->branch_id, $delivery->branch_id);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame([
            'id' => 'MHIS-'.$transaction->id,
            'amount' => 150000,
            'status' => 'PAID',
            'created' => $transaction->created_at?->toISOString(),
            'paid_at' => $transaction->paid_at?->toISOString(),
            'updated' => $transaction->updated_at?->toISOString(),
            'currency' => 'IDR',
            'bank_code' => 'MANDIRI',
            'payment_id' => 'REQ-WEBHOOK-001',
            'description' => 'Webhook Test Invoice',
            'external_id' => 'WEBHOOK-BILL-001',
            'paid_amount' => 150000,
            'merchant_name' => config('app.name'),
            'payment_method' => 'BANK_TRANSFER',
            'payment_channel' => 'MANDIRI',
            'payment_destination' => '85765081234567890',
        ], $delivery->payload);
        $this->assertArrayNotHasKey('user_id', $delivery->payload);
        $this->assertArrayNotHasKey('is_high', $delivery->payload);
        Queue::assertPushed(SendBranchWebhookDelivery::class, 1);
    }

    public function test_paid_callback_without_branch_webhook_is_recorded_as_skipped(): void
    {
        Queue::fake([SendBranchWebhookDelivery::class]);
        $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->callbackPayload())->assertOk();

        $this->assertDatabaseHas('branch_webhook_deliveries', [
            'status' => 'skipped',
            'webhook_url' => null,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_duplicate_paid_callback_does_not_create_a_second_delivery_or_job(): void
    {
        Queue::fake([SendBranchWebhookDelivery::class]);
        $this->createInvoiceWithVirtualAccount('https://branch.example/callback');
        $payload = $this->callbackPayload();

        $this->postCallback($payload)->assertOk()->assertJsonPath('outcome', 'processed');
        $this->postCallback($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');

        $this->assertDatabaseCount('branch_webhook_deliveries', 1);
        Queue::assertPushed(SendBranchWebhookDelivery::class, 1);
    }

    public function test_delivery_job_posts_snapshot_and_records_successful_response(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://branch.example/callback' => Http::response(['received' => true], 200),
        ]);
        $delivery = $this->createDelivery();

        (new SendBranchWebhookDelivery($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://branch.example/callback'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Delivery-ID', (string) $delivery->id)
            && $request->data() === $delivery->payload);
    }

    public function test_server_error_keeps_delivery_pending_for_queue_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://branch.example/callback' => Http::response(['error' => 'temporary'], 503),
        ]);
        $delivery = $this->createDelivery();

        try {
            (new SendBranchWebhookDelivery($delivery->id))->handle();
            $this->fail('A retryable server response should be thrown back to the queue.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Branch webhook returned a retryable HTTP status.', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(503, $delivery->response_status);
    }

    public function test_client_error_marks_delivery_failed_without_retrying(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://branch.example/callback' => Http::response(['error' => 'invalid'], 422),
        ]);
        $delivery = $this->createDelivery();

        (new SendBranchWebhookDelivery($delivery->id))->handle();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(422, $delivery->response_status);
    }

    public function test_super_admin_can_manually_retry_a_failed_delivery(): void
    {
        Queue::fake([SendBranchWebhookDelivery::class]);
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $delivery = $this->createDelivery(status: 'failed');

        $this->actingAs($user)
            ->post(route('payment-transactions.callback.retry', $delivery->paymentTransaction))
            ->assertRedirect()
            ->assertSessionHas('status', 'Callback ke cabang telah dimasukkan ke antrean.');

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertNull($delivery->delivered_at);
        Queue::assertPushed(SendBranchWebhookDelivery::class, fn (SendBranchWebhookDelivery $job): bool => $job->deliveryId === $delivery->id);
    }

    public function test_guest_cannot_manually_retry_a_branch_delivery(): void
    {
        $delivery = $this->createDelivery(status: 'failed');

        $this->post(route('payment-transactions.callback.retry', $delivery->paymentTransaction))
            ->assertRedirect(route('login'));

        $this->assertSame('failed', $delivery->refresh()->status);
    }

    public function test_non_super_admin_cannot_manually_retry_a_branch_delivery(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);
        $delivery = $this->createDelivery(status: 'failed');

        $this->actingAs($user)
            ->post(route('payment-transactions.callback.retry', $delivery->paymentTransaction))
            ->assertForbidden();

        $this->assertSame('failed', $delivery->refresh()->status);
    }

    public function test_manual_retry_reports_missing_branch_webhook_url(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $delivery = $this->createDelivery(status: 'failed', webhookUrl: null);

        $this->actingAs($user)
            ->post(route('payment-transactions.callback.retry', $delivery->paymentTransaction))
            ->assertRedirect()
            ->assertSessionHas('error', 'URL callback cabang belum dikonfigurasi.');

        $this->assertSame('skipped', $delivery->refresh()->status);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array{event: string, data: array<string, string>, timestamp: string}
     */
    private function callbackPayload(array $overrides = []): array
    {
        return [
            'event' => 'va.paid',
            'data' => $overrides + [
                'virtualAccountNo' => '85765081234567890',
                'customerNo' => '081234567890',
                'virtualAccountName' => 'Budi Santoso',
                'billCode' => 'WEBHOOK-BILL-001',
                'totalAmount' => '150000.00',
                'paidAmount' => '150000.00',
                'paymentRequestId' => 'REQ-WEBHOOK-001',
                'referenceNo' => 'REF-WEBHOOK-001',
                'status' => 'PAID',
            ],
            'timestamp' => '2026-10-12T08:00:00Z',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postCallback(array $payload): TestResponse
    {
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $apiKey = 'test-mandiri-callback-api-key';
        $bank->forceFill(['callback_api_key_hash' => hash('sha256', $apiKey)])->save();

        return $this->call(
            'POST',
            route('api.v1.callbacks.mandiri.virtual-account-paid'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_API_KEY' => $apiKey,
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    private function createInvoiceWithVirtualAccount(?string $webhookUrl = null): Invoice
    {
        $branch = Branch::query()->create([
            'name' => 'Webhook Branch',
            'code' => 'WHB001',
            'va_fixed_prefix' => '857650',
            'va_dynamic_prefix' => '857659',
            'api_key' => hash('sha256', 'webhook-test-branch'),
            'webhook_url' => $webhookUrl,
            'is_active' => true,
        ]);
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-WEBHOOK-001',
            'source' => 'api',
            'bill_code' => 'WEBHOOK-BILL-001',
            'bill_name' => 'Webhook Test Invoice',
            'customer_name' => 'Budi Santoso',
            'amount' => '150000.00',
            'fee_amount' => '0.00',
            'total_amount' => '150000.00',
            'status' => 'pending',
            'expired_at' => '2026-10-20 23:59:00',
        ]);
        $invoice->virtualAccount()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => '081234567890',
            'virtual_account_no' => '85765081234567890',
            'virtual_account_name' => 'Budi Santoso',
            'current_amount' => '150000.00',
            'expired_at' => $invoice->expired_at,
            'status' => 'active',
        ]);

        return $invoice->load('virtualAccount');
    }

    private function createDelivery(
        string $status = 'pending',
        ?string $webhookUrl = 'https://branch.example/callback',
    ): BranchWebhookDelivery {
        $invoice = $this->createInvoiceWithVirtualAccount($webhookUrl);
        $transaction = PaymentTransaction::query()->create([
            'branch_id' => $invoice->branch_id,
            'bank_id' => $invoice->virtualAccount->bank_id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $invoice->virtualAccount->id,
            'paid_amount' => '150000.00',
            'payment_reference_no' => 'REF-DELIVERY-001',
            'payment_request_id' => 'REQ-DELIVERY-001',
            'paid_at' => '2026-10-12 08:00:00',
            'raw_payload' => [],
        ]);

        return $transaction->webhookDelivery()->create([
            'branch_id' => $invoice->branch_id,
            'webhook_url' => $webhookUrl,
            'status' => $status,
            'attempts' => 0,
            'payload' => ['id' => 'MHIS-'.$transaction->id, 'status' => 'PAID'],
        ]);
    }
}
