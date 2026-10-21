<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceDashboardTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_super_admin_can_open_invoice_form_with_random_external_id_and_branch_choices(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();

        $response = $this->actingAs($user)->get(route('invoices.create'));

        $response->assertOk()
            ->assertSee('Bintaro')
            ->assertSee('Mandiri');
        $externalId = $response->viewData('externalId');
        $this->assertMatchesRegularExpression('/^mutiar-[a-z0-9]{16}$/', $externalId);
        $response->assertSee('value="'.$externalId.'"', false);
        $this->assertSame($branch->id, $response->viewData('branches')->first()->id);
        $this->assertSame($bank->id, $response->viewData('banks')->first()->id);
    }

    public function test_super_admin_can_create_dashboard_invoice_and_dynamic_va_for_selected_branch(): void
    {
        Http::fake([
            'https://h2h.test/api/internal/va/create' => Http::response(['status' => 'success'], 200),
        ]);
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $branch = $this->createBranch();
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'external_id' => 'mutiar-test12345678',
            'amount' => '125000.00',
            'description' => 'Tagihan pendaftaran',
            'expired_at' => '2030-06-30T23:59',
        ]);

        $invoice = Invoice::query()->firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', 'Invoice dan Dynamic VA berhasil dibuat.');
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'branch_id' => $branch->id,
            'source' => 'dashboard',
            'bill_code' => 'mutiar-test12345678',
            'bill_name' => 'Tagihan pendaftaran',
            'amount' => '125000.00',
            'total_amount' => '125000.00',
            'expired_at' => '2030-06-30 23:59:00',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'invoice_id' => $invoice->id,
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'virtual_account_name' => 'Tagihan pendaftaran',
            'status' => 'active',
        ]);
        $this->assertMatchesRegularExpression(
            '/^85765991\d{8}$/',
            VirtualAccount::query()->where('invoice_id', $invoice->id)->value('virtual_account_no'),
        );
    }

    public function test_invoice_creation_requires_a_branch_and_valid_invoice_details(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $bank = Bank::factory()->create();

        $this->actingAs($user)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), [
                'bank_id' => $bank->id,
                'external_id' => 'mutiar-test12345678',
                'amount' => '0',
                'description' => '',
                'expired_at' => '2020-01-01T00:00',
            ])
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors(['branch_id', 'amount', 'description', 'expired_at']);

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_user_without_invoice_create_permission_cannot_create_invoice(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)->get(route('invoices.create'))->assertForbidden();
        $this->actingAs($user)->post(route('invoices.store'), [])->assertForbidden();
        $this->assertDatabaseCount('invoices', 0);
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
}
