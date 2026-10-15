<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Student;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTransactionIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_payment_transactions_and_open_transaction_details(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $transaction = $this->createTransaction();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee('Transactions')
            ->assertSee('REF-TRANSACTION-LIST-001')
            ->assertSee('INV-TRANSACTION-LIST-001')
            ->assertSee('BILL-TRANSACTION-LIST-001')
            ->assertSee('8576501012345678')
            ->assertSee('01125014')
            ->assertSee('Cabang Transaksi')
            ->assertSee('Bank Transaksi')
            ->assertSee('Rp. 250,000', false)
            ->assertSee(route('transactions.show', $transaction), false);
    }

    public function test_payment_transaction_search_filters_by_reference_invoice_bill_code_and_virtual_account(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $matchingTransaction = $this->createTransaction();
        $otherTransaction = $this->createTransaction(
            reference: 'REF-OTHER-002',
            invoiceNumber: 'INV-OTHER-002',
            billCode: 'BILL-OTHER-002',
            virtualAccountNumber: '8576599223456789',
        );

        foreach ([
            'REF-TRANSACTION-LIST-001',
            'INV-TRANSACTION-LIST-001',
            'BILL-TRANSACTION-LIST-001',
            '8576501012345678',
        ] as $search) {
            $this->actingAs($user)
                ->get(route('transactions.index', ['search' => $search]))
                ->assertOk()
                ->assertSee($matchingTransaction->payment_reference_no)
                ->assertDontSee($otherTransaction->payment_reference_no);
        }
    }

    public function test_guest_cannot_view_payment_transaction_list(): void
    {
        $this->get(route('transactions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_view_payment_transaction_list(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertForbidden();
    }

    private function createTransaction(
        string $reference = 'REF-TRANSACTION-LIST-001',
        string $invoiceNumber = 'INV-TRANSACTION-LIST-001',
        string $billCode = 'BILL-TRANSACTION-LIST-001',
        string $virtualAccountNumber = '8576501012345678',
        string $nis = '01125014',
        string $vaType = 'fixed',
    ): PaymentTransaction {
        $sequence = Branch::query()->count() + 1;
        $branch = Branch::query()->create([
            'name' => 'Cabang Transaksi',
            'code' => 'TRX'.$sequence,
            'va_fixed_prefix' => sprintf('857650%02d', $sequence),
            'va_dynamic_prefix' => sprintf('857659%02d', $sequence),
            'api_key' => hash('sha256', (string) $sequence),
            'is_active' => true,
        ]);
        $bank = Bank::factory()->create(['name' => 'Bank Transaksi']);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => $nis,
            'nisn' => 'NISN-'.$nis,
            'name' => 'Pelanggan Uji',
            'fixed_va_number' => 'FIXED-'.$sequence,
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'va_type' => $vaType,
            'invoice_number' => $invoiceNumber,
            'source' => 'api',
            'bill_code' => $billCode,
            'bill_name' => 'Tagihan Transaksi',
            'customer_name' => 'Pelanggan Uji',
            'email' => 'payer@example.com',
            'amount' => '250000.00',
            'fee_amount' => '0.00',
            'total_amount' => '250000.00',
            'status' => 'paid',
            'paid_at' => '2026-10-14 09:00:00',
        ]);
        $virtualAccount = VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'va_type' => $vaType,
            'partner_service_id' => '85765',
            'customer_no' => $virtualAccountNumber,
            'virtual_account_no' => $virtualAccountNumber,
            'virtual_account_name' => 'Pelanggan Uji',
            'current_amount' => '250000.00',
            'status' => 'active',
        ]);

        return PaymentTransaction::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $virtualAccount->id,
            'paid_amount' => '250000.00',
            'payment_reference_no' => $reference,
            'payment_request_id' => 'REQ-'.$reference,
            'channel' => 'VA',
            'paid_at' => '2026-10-14 09:00:00',
        ]);
    }

    public function test_transaction_list_only_includes_fixed_virtual_accounts(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $fixedTransaction = $this->createTransaction();
        $dynamicTransaction = $this->createTransaction(
            reference: 'REF-DYNAMIC-002',
            invoiceNumber: 'INV-DYNAMIC-002',
            billCode: 'BILL-DYNAMIC-002',
            virtualAccountNumber: '8576599223456789',
            vaType: 'dynamic',
        );

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertSee($fixedTransaction->payment_reference_no)
            ->assertDontSee($dynamicTransaction->payment_reference_no);
    }

    public function test_transaction_search_by_nis_shows_all_matching_fixed_va_payments_only(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $firstMatchingTransaction = $this->createTransaction();
        $secondMatchingTransaction = PaymentTransaction::query()->create([
            'branch_id' => $firstMatchingTransaction->branch_id,
            'bank_id' => $firstMatchingTransaction->bank_id,
            'invoice_id' => $firstMatchingTransaction->invoice_id,
            'virtual_account_id' => $firstMatchingTransaction->virtual_account_id,
            'paid_amount' => '125000.00',
            'payment_reference_no' => 'REF-SAME-NIS-002',
            'payment_request_id' => 'REQ-REF-SAME-NIS-002',
            'channel' => 'VA',
            'paid_at' => '2026-10-15 09:00:00',
        ]);
        $otherStudentTransaction = $this->createTransaction(
            reference: 'REF-OTHER-NIS-003',
            invoiceNumber: 'INV-OTHER-NIS-003',
            billCode: 'BILL-OTHER-NIS-003',
            virtualAccountNumber: '8576501033456789',
            nis: '09999999',
        );
        $dynamicTransaction = $this->createTransaction(
            reference: 'REF-DYNAMIC-NIS-004',
            invoiceNumber: 'INV-DYNAMIC-NIS-004',
            billCode: 'BILL-DYNAMIC-NIS-004',
            virtualAccountNumber: '8576591043456789',
            vaType: 'dynamic',
        );

        $this->actingAs($user)
            ->get(route('transactions.index', ['search' => '01125014']))
            ->assertOk()
            ->assertSee($firstMatchingTransaction->payment_reference_no)
            ->assertSee($secondMatchingTransaction->payment_reference_no)
            ->assertDontSee($otherStudentTransaction->payment_reference_no)
            ->assertDontSee($dynamicTransaction->payment_reference_no);
    }
}
