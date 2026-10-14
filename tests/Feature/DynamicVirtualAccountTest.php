<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicVirtualAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_non_super_admin_cannot_access_dynamic_virtual_accounts(): void
    {
        $this->get(route('virtual-accounts.dynamic.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('virtual-accounts.dynamic.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_dynamic_va_and_invoice_data_but_not_fixed_va(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'name' => 'Cabang Dynamic',
            'code' => 'DYN',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => str_repeat('d', 64),
            'is_active' => true,
        ]);
        $bank = Bank::factory()->create([
            'name' => 'Bank Dynamic',
        ]);
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-DYNAMIC-001',
            'source' => 'api',
            'bill_code' => 'BILL-DYNAMIC-001',
            'bill_name' => 'Biaya Pendaftaran',
            'customer_name' => 'Siswa Dynamic',
            'email' => 'wali@example.com',
            'amount' => '250000.00',
            'fee_amount' => '0.00',
            'total_amount' => '250000.00',
            'status' => 'paid',
            'expired_at' => now()->addDay(),
            'paid_at' => now(),
        ]);
        $invoice->virtualAccount()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => '8576599112345001',
            'virtual_account_no' => '8576599112345001',
            'virtual_account_name' => 'Siswa Dynamic',
            'current_amount' => '250000.00',
            'expired_at' => now()->addDay(),
            'status' => 'active',
        ]);
        VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'va_type' => 'fixed',
            'partner_service_id' => '85765',
            'customer_no' => '8576501012345001',
            'virtual_account_no' => '8576501012345001',
            'virtual_account_name' => 'Siswa Fixed',
            'current_amount' => '0.00',
            'expired_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('virtual-accounts.dynamic.index'))
            ->assertOk()
            ->assertSee('8576599112345001')
            ->assertSee('INV-DYNAMIC-001')
            ->assertSee('BILL-DYNAMIC-001')
            ->assertSee('Biaya Pendaftaran')
            ->assertSee('Siswa Dynamic')
            ->assertSee('Bank Dynamic')
            ->assertSee('Cabang Dynamic')
            ->assertSee('Rp 250.000,00', false)
            ->assertSee('Lunas')
            ->assertSee(route('invoices.show', $invoice))
            ->assertDontSee('8576501012345001')
            ->assertDontSee('Siswa Fixed');
    }
}
