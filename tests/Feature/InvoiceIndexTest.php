<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('invoices.index'))->assertRedirect(route('login'));
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)->get(route('invoices.index'))->assertForbidden();
    }

    public function test_invoice_list_shows_payment_transaction_results(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = Branch::query()->create([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => 'branch-api-key-1234567890',
            'is_active' => true,
        ]);
        $bank = Bank::query()->first() ?? Bank::factory()->create();
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-20271001-ABCDEFGH',
            'source' => 'api',
            'bill_code' => 'SPP-01',
            'bill_name' => 'SPP Januari',
            'customer_name' => 'Budi Santoso',
            'email' => 'wali@example.com',
            'amount' => '1500000.00',
            'fee_amount' => '0.00',
            'total_amount' => '1500000.00',
            'status' => 'paid',
            'expired_at' => now()->addDay(),
            'paid_at' => now(),
        ]);
        $invoice->virtualAccount()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => '8576599112345678',
            'virtual_account_no' => '8576599112345678',
            'virtual_account_name' => 'Budi Santoso',
            'current_amount' => '1500000.00',
            'expired_at' => now()->addDay(),
            'status' => 'active',
        ]);
        PaymentTransaction::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $invoice->virtualAccount->id,
            'paid_amount' => '1500000.00',
            'payment_reference_no' => 'REF-998877',
            'channel' => 'ATM',
            'paid_at' => now(),
        ]);

        $this->actingAs($user)->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('SPP-01')
            ->assertSee('wali@example.com')
            ->assertSee('SPP Januari')
            ->assertSee('Rp. 1,500,000', false)
            ->assertSee('bx-show', false)
            ->assertSee(route('invoices.show', $invoice))
            ->assertDontSee('8576599112345678');

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('INV-20271001-ABCDEFGH')
            ->assertSee('8576599112345678')
            ->assertSee('REF-998877')
            ->assertSee('Rp. 1,500,000', false);
    }

    public function test_pending_invoice_past_expiry_is_listed_as_expired(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = Branch::query()->create([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => 'branch-api-key-1234567890',
            'is_active' => true,
        ]);
        Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-20271001-ZZZZZZZZ',
            'source' => 'api',
            'bill_code' => 'OLD-01',
            'bill_name' => 'Lama',
            'customer_name' => 'Budi',
            'email' => 'a@example.com',
            'amount' => '1000.00',
            'fee_amount' => '0.00',
            'total_amount' => '1000.00',
            'status' => 'pending',
            'expired_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Expired');

        $this->actingAs($user)->get(route('invoices.index', ['statuses' => ['expired']]))
            ->assertOk()
            ->assertSee('OLD-01');

        $this->actingAs($user)->get(route('invoices.index', ['statuses' => ['pending']]))
            ->assertOk()
            ->assertDontSee('OLD-01');
    }

    public function test_invoice_search_supports_bill_code_invoice_number_and_email(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $target = $this->createInvoice($branch, [
            'bill_code' => 'SEAMO-SEARCH-01',
            'invoice_number' => 'INV-SEARCH-01',
            'email' => 'payer-search@example.com',
        ]);
        $other = $this->createInvoice($branch, [
            'bill_code' => 'SEAMO-SEARCH-02',
            'invoice_number' => 'INV-SEARCH-02',
            'email' => 'another@example.com',
        ]);

        foreach ([
            ['bill_code', $target->bill_code],
            ['invoice_number', $target->invoice_number],
            ['email', $target->email],
        ] as [$searchBy, $search]) {
            $response = $this->actingAs($user)->get(route('invoices.index', [
                'search_by' => $searchBy,
                'search' => $search,
            ]));

            $response->assertOk()
                ->assertSee($target->bill_code)
                ->assertDontSee($other->bill_code);
        }
    }

    public function test_date_status_source_and_amount_filters_are_applied_together(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $matching = $this->createInvoice($branch, [
            'bill_code' => 'MATCHING-INVOICE',
            'source' => 'api',
            'status' => 'paid',
            'paid_at' => '2026-10-01 00:00:00',
            'created_at' => '2026-10-02 23:59:59',
            'expired_at' => '2026-10-03 23:59:59',
            'total_amount' => '250.00',
        ]);
        $this->createInvoice($branch, [
            'bill_code' => 'WRONG-DATE',
            'source' => 'api',
            'status' => 'paid',
            'paid_at' => '2026-10-01 00:00:00',
            'created_at' => '2026-10-04 00:00:00',
            'expired_at' => '2026-10-03 23:59:59',
            'total_amount' => '250.00',
        ]);
        $this->createInvoice($branch, [
            'bill_code' => 'WRONG-STATUS',
            'source' => 'api',
            'status' => 'pending',
            'paid_at' => '2026-10-01 00:00:00',
            'created_at' => '2026-10-02 23:59:59',
            'expired_at' => '2026-10-03 23:59:59',
            'total_amount' => '250.00',
        ]);
        $this->createInvoice($branch, [
            'bill_code' => 'WRONG-SOURCE',
            'source' => 'dashboard',
            'status' => 'paid',
            'paid_at' => '2026-10-01 00:00:00',
            'created_at' => '2026-10-02 23:59:59',
            'expired_at' => '2026-10-03 23:59:59',
            'total_amount' => '250.00',
        ]);

        $this->actingAs($user)->get(route('invoices.index', [
            'date_filters' => ['paid_at', 'created_at', 'expired_at'],
            'date_ranges' => [
                'paid_at' => ['from' => '2026-10-01', 'to' => '2026-10-01'],
                'created_at' => ['from' => '2026-10-02', 'to' => '2026-10-02'],
                'expired_at' => ['from' => '2026-10-03', 'to' => '2026-10-03'],
            ],
            'statuses' => ['paid'],
            'created_from' => ['api'],
            'amount_operator' => 'equals',
            'amount' => '250',
        ]))
            ->assertOk()
            ->assertSee($matching->bill_code)
            ->assertDontSee('WRONG-DATE')
            ->assertDontSee('WRONG-STATUS')
            ->assertDontSee('WRONG-SOURCE');
    }

    public function test_amount_filter_supports_equals_greater_than_and_less_than(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $lower = $this->createInvoice($branch, ['bill_code' => 'AMOUNT-100', 'total_amount' => '100.00']);
        $equal = $this->createInvoice($branch, ['bill_code' => 'AMOUNT-250', 'total_amount' => '250.00']);
        $higher = $this->createInvoice($branch, ['bill_code' => 'AMOUNT-500', 'total_amount' => '500.00']);

        $this->actingAs($user)->get(route('invoices.index', ['amount_operator' => 'equals', 'amount' => 250]))
            ->assertOk()
            ->assertSee($equal->bill_code)
            ->assertDontSee($lower->bill_code)
            ->assertDontSee($higher->bill_code);

        $this->actingAs($user)->get(route('invoices.index', ['amount_operator' => 'greater', 'amount' => 250]))
            ->assertOk()
            ->assertSee($higher->bill_code)
            ->assertDontSee($lower->bill_code)
            ->assertDontSee($equal->bill_code);

        $this->actingAs($user)->get(route('invoices.index', ['amount_operator' => 'less', 'amount' => 250]))
            ->assertOk()
            ->assertSee($lower->bill_code)
            ->assertDontSee($equal->bill_code)
            ->assertDontSee($higher->bill_code);
    }

    public function test_pagination_limits_rows_and_preserves_filters(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();

        for ($index = 1; $index <= 17; $index++) {
            $this->createInvoice($branch, ['source' => $index === 17 ? 'dashboard' : 'api']);
        }

        $response = $this->actingAs($user)->get(route('invoices.index', [
            'created_from' => ['api'],
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertSee('created_from%5B0%5D=api', false);

        $invoices = $response->viewData('invoices');
        $this->assertSame(2, $invoices->currentPage());
        $this->assertSame(1, $invoices->count());
        $this->assertSame(16, $invoices->total());
    }

    public function test_invalid_date_ranges_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->createBranch();

        $this->actingAs($user)->get(route('invoices.index', [
            'date_filters' => ['created_at'],
            'date_ranges' => [
                'created_at' => ['from' => '2026-10-14', 'to' => '2026-10-01'],
            ],
        ]))->assertSessionHasErrors('date_ranges.created_at.to');
    }

    private function createBranch(): Branch
    {
        return Branch::query()->create([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => 'branch-api-key-1234567890',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(Branch $branch, array $overrides = []): Invoice
    {
        $number = Invoice::query()->count() + 1;
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $invoice = Invoice::query()->create($overrides + [
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-FILTER-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'source' => 'api',
            'bill_code' => 'BILL-FILTER-'.$number,
            'bill_name' => 'Filter test invoice',
            'customer_name' => 'Test Payer',
            'email' => 'filter@example.com',
            'amount' => '250.00',
            'fee_amount' => '0.00',
            'total_amount' => '250.00',
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);

        if ($createdAt !== null) {
            $invoice->forceFill(['created_at' => $createdAt])->save();
        }

        return $invoice;
    }
}
