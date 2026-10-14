<?php

namespace Tests\Feature;

use App\Jobs\VoidExpiredVirtualAccount;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\Invoice;
use App\Models\VirtualAccount;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireDueVirtualAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_queues_only_expired_active_vas_with_unpaid_invoices(): void
    {
        $this->travelTo('2026-10-14 15:00:00');
        $expiredDynamic = $this->createVirtualAccount('dynamic', '2026-10-14 14:59:00');
        $expiredFixed = $this->createVirtualAccount('fixed', '2026-10-14 14:59:00');
        $this->createVirtualAccount('dynamic', '2026-10-14 14:59:00', 'paid');
        $this->createVirtualAccount('dynamic', '2026-10-14 15:01:00');
        Queue::fake([VoidExpiredVirtualAccount::class]);

        $this->artisan('virtual-accounts:expire-due')
            ->expectsOutput('Queued 2 expired virtual account(s) for deactivation.')
            ->assertSuccessful();

        Queue::assertPushed(
            VoidExpiredVirtualAccount::class,
            fn (VoidExpiredVirtualAccount $job): bool => in_array(
                $job->virtualAccountId,
                [$expiredDynamic->id, $expiredFixed->id],
                true,
            ),
        );
    }

    public function test_job_deactivates_expired_dynamic_va_and_marks_pending_invoice_expired(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['status' => 'success'], 200),
        ]);
        $this->configureH2h();
        $this->travelTo('2026-10-14 15:00:00');
        $virtualAccount = $this->createVirtualAccount('dynamic', '2026-10-14 14:59:00');

        $this->runExpiryJob($virtualAccount);

        $this->assertSame('inactive', $virtualAccount->refresh()->status);
        $this->assertSame('expired', $virtualAccount->invoice->refresh()->status);
        $this->assertSame('2026-10-14 14:59:00', $virtualAccount->expired_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('h2h_api_logs', [
            'virtual_account_id' => $virtualAccount->id,
            'invoice_id' => $virtualAccount->invoice_id,
            'response_status' => 200,
            'http_method' => 'PUT',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://h2h.test/api/internal/va/update'
            && $request->data() === [
                'virtualAccountNo' => $virtualAccount->virtual_account_no,
                'totalAmount' => '250000.00',
                'expiredAt' => '2026-10-14 14:59:00',
                'status' => 'INACTIVE',
            ]);
    }

    public function test_job_deactivates_expired_fixed_va_without_an_invoice(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['status' => 'success'], 200),
        ]);
        $this->configureH2h();
        $this->travelTo('2026-10-14 15:00:00');
        $virtualAccount = $this->createVirtualAccount('fixed', '2026-10-14 14:59:00');

        $this->runExpiryJob($virtualAccount);

        $this->assertSame('inactive', $virtualAccount->refresh()->status);
        $this->assertNull($virtualAccount->invoice_id);
        $this->assertDatabaseHas('h2h_api_logs', [
            'virtual_account_id' => $virtualAccount->id,
            'invoice_id' => null,
            'response_status' => 200,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->data()['status'] === 'INACTIVE');
    }

    public function test_job_retries_server_error_and_accepts_verification_required_va_on_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::sequence()
                ->push(['message' => 'temporary'], 500)
                ->push(['status' => 'success'], 200),
        ]);
        $this->configureH2h();
        $this->travelTo('2026-10-14 15:00:00');
        $virtualAccount = $this->createVirtualAccount('dynamic', '2026-10-14 14:59:00');
        $job = new VoidExpiredVirtualAccount($virtualAccount->id);

        try {
            $job->handle(app(VirtualAccountServiceInterface::class));
            $this->fail('A server error should be thrown back to the queue for retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Virtual account expiry returned [server_error].', $exception->getMessage());
        }

        $this->assertSame('verification_required', $virtualAccount->refresh()->status);

        $job->handle(app(VirtualAccountServiceInterface::class));

        $this->assertSame('inactive', $virtualAccount->refresh()->status);
        $this->assertSame('expired', $virtualAccount->invoice->refresh()->status);
        $this->assertSame(2, H2hApiLog::query()->where('virtual_account_id', $virtualAccount->id)->count());
        Http::assertSentCount(2);
    }

    public function test_expiry_job_does_not_deactivate_a_virtual_account_before_its_due_time(): void
    {
        Http::preventStrayRequests();
        $this->configureH2h();
        $this->travelTo('2026-10-14 15:00:00');
        $virtualAccount = $this->createVirtualAccount('dynamic', '2026-10-14 15:01:00');

        $this->runExpiryJob($virtualAccount);

        $this->assertSame('active', $virtualAccount->refresh()->status);
        $this->assertSame('pending', $virtualAccount->invoice->refresh()->status);
        $this->assertDatabaseCount('h2h_api_logs', 0);
    }

    private function runExpiryJob(VirtualAccount $virtualAccount): void
    {
        (new VoidExpiredVirtualAccount($virtualAccount->id))
            ->handle(app(VirtualAccountServiceInterface::class));
    }

    private function configureH2h(): void
    {
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);
    }

    private function createVirtualAccount(
        string $vaType,
        string $expiredAt,
        string $invoiceStatus = 'pending',
    ): VirtualAccount {
        $sequence = Branch::query()->count() + 1;
        $branch = Branch::query()->create([
            'name' => 'Expiry Branch '.$sequence,
            'code' => 'EXP'.$sequence,
            'va_fixed_prefix' => sprintf('857650%02d', $sequence),
            'va_dynamic_prefix' => sprintf('857659%02d', $sequence),
            'api_key' => hash('sha256', 'expiry-branch-'.$sequence),
            'is_active' => true,
        ]);
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $invoice = $vaType === 'dynamic'
            ? Invoice::query()->create([
                'branch_id' => $branch->id,
                'va_type' => 'dynamic',
                'invoice_number' => 'INV-EXPIRY-'.$sequence,
                'source' => 'test',
                'bill_code' => 'EXPIRY-BILL-'.$sequence,
                'bill_name' => 'Expiry Test Invoice',
                'customer_name' => 'Test Customer',
                'amount' => '250000.00',
                'fee_amount' => '0.00',
                'total_amount' => '250000.00',
                'status' => $invoiceStatus,
                'expired_at' => $expiredAt,
            ])
            : null;

        return VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice?->id,
            'va_type' => $vaType,
            'partner_service_id' => '85765',
            'customer_no' => '857650000000000'.$sequence,
            'virtual_account_no' => '857650000000000'.$sequence,
            'virtual_account_name' => 'Test Customer',
            'current_amount' => $invoice?->total_amount ?? '0.00',
            'expired_at' => $expiredAt,
            'status' => 'active',
        ]);
    }
}
