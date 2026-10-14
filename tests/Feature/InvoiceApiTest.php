<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'branch-api-key-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);
    }

    public function test_request_without_valid_api_key_is_rejected(): void
    {
        $this->postJson(route('api.v1.invoices.store'), $this->payload())->assertUnauthorized();
    }

    public function test_invoice_is_created_with_a_random_dynamic_va_using_the_branch_prefix(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $branch = $this->createBranch();

        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '1510000.00')
            ->assertJsonPath('data.virtual_account.va_type', 'dynamic')
            ->assertJsonPath('data.virtual_account.status', 'active');

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($branch->id, $invoice->branch_id);

        $virtualAccount = VirtualAccount::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^85765991\d{8}$/', $virtualAccount->virtual_account_no);
    }

    public function test_each_invoice_receives_a_different_va_number(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $this->createBranch();

        foreach (['A', 'B', 'C'] as $code) {
            $this->postJson(route('api.v1.invoices.store'), $this->payload(['bill_code' => 'SPP-'.$code]), $this->headers())
                ->assertCreated();
        }

        $this->assertSame(3, VirtualAccount::query()->distinct('virtual_account_no')->count('virtual_account_no'));
    }

    public function test_generator_skips_numbers_that_are_already_taken(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        // A prefix leaving a single random digit makes collisions with existing VAs very likely.
        $branch = $this->createBranch(['va_dynamic_prefix' => '857659910000000']);

        for ($digit = 0; $digit <= 2; $digit++) {
            VirtualAccount::query()->create([
                'branch_id' => $branch->id,
                'bank_id' => Bank::query()->value('id'),
                'va_type' => 'fixed',
                'partner_service_id' => '85765',
                'customer_no' => '857659910000000'.$digit,
                'virtual_account_no' => '857659910000000'.$digit,
                'virtual_account_name' => 'Taken',
                'current_amount' => '0.00',
                'expired_at' => now()->addYear(),
                'status' => 'active',
            ]);
        }

        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())->assertCreated();

        $this->assertDatabaseCount('virtual_accounts', 4);
        $this->assertSame(4, VirtualAccount::query()->distinct()->count('virtual_account_no'));
        $this->assertDatabaseMissing('virtual_accounts', ['invoice_id' => null, 'virtual_account_no' => VirtualAccount::query()->whereNotNull('invoice_id')->value('virtual_account_no')]);
    }

    public function test_duplicate_bill_code_is_rejected(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $this->createBranch();

        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())->assertCreated();
        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bill_code');

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_validation_errors_are_returned(): void
    {
        $this->createBranch();

        $this->postJson(route('api.v1.invoices.store'), [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bill_code', 'bill_name', 'customer_name', 'email', 'amount', 'expired_at']);
    }

    public function test_email_is_stored_and_must_be_valid(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200)]);
        $this->createBranch();

        $this->postJson(route('api.v1.invoices.store'), $this->payload(['email' => 'bukan-email']), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.email', 'wali.budi@example.com');

        $this->assertDatabaseHas('invoices', ['email' => 'wali.budi@example.com']);
    }

    public function test_bank_rejection_is_reported_and_marks_the_va_failed(): void
    {
        Http::fake(['https://h2h.test/api/internal/va/create' => Http::response(['message' => 'rejected'], 422)]);
        $this->createBranch();

        $this->postJson(route('api.v1.invoices.store'), $this->payload(), $this->headers())
            ->assertUnprocessable()
            ->assertJsonPath('data.virtual_account.status', 'failed');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-API-KEY' => self::API_KEY];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'bill_code' => 'SPP-2027-01',
            'bill_name' => 'SPP Januari 2027',
            'customer_name' => 'Budi Santoso',
            'email' => 'wali.budi@example.com',
            'amount' => 1500000,
            'fee_amount' => 10000,
            'expired_at' => '2999-06-30 23:59',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBranch(array $overrides = []): Branch
    {
        return Branch::query()->create($overrides + [
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => self::API_KEY,
            'is_active' => true,
        ]);
    }
}
