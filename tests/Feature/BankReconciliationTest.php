<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankReconciliation;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_reconciliation_page_and_download_csv_template(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $bank = Bank::factory()->create(['name' => 'Bank Rekonsiliasi']);

        $this->actingAs($user)
            ->get(route('reconciliations.index'))
            ->assertOk()
            ->assertSee('Unggah Laporan Bank')
            ->assertSee('reference_no,amount,paid_at')
            ->assertSee($bank->name);

        $this->get(route('reconciliations.template'))
            ->assertOk()
            ->assertDownload('bank-reconciliation-template.csv');
    }

    public function test_csv_reconciliation_persists_matching_results_and_shows_exceptions(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $bank = Bank::factory()->create();
        $this->createTransaction($bank, 'REF-MATCH-001');
        $this->createTransaction($bank, 'REF-MISMATCH-002');
        $this->createTransaction($bank, 'REF-MISSING-004');
        $csv = implode("\n", [
            'reference_no,amount,paid_at',
            'REF-MATCH-001,250000.00,2026-10-14T10:00:00+07:00',
            'REF-MISMATCH-002,100.00,2026-10-14T10:01:00+07:00',
            'REF-UNKNOWN-003,500.00,2026-10-14T10:02:00+07:00',
            'REF-MATCH-001,250000.00,2026-10-14T10:03:00+07:00',
        ]);

        $response = $this->actingAs($user)->post(route('reconciliations.store'), [
            'bank_id' => $bank->id,
            'period_from' => '2026-10-14',
            'period_to' => '2026-10-14',
            'statement_file' => File::createWithContent('mandiri-report.csv', $csv),
        ]);
        $response->assertSessionHasNoErrors();

        $reconciliation = BankReconciliation::query()->firstOrFail();
        $response->assertRedirect(route('reconciliations.show', $reconciliation))
            ->assertSessionHas('status', 'Laporan berhasil direkonsiliasi.');

        $this->assertDatabaseHas('bank_reconciliations', [
            'id' => $reconciliation->id,
            'bank_id' => $bank->id,
            'uploaded_by' => $user->id,
            'matched_count' => 1,
            'amount_mismatch_count' => 1,
            'not_found_count' => 1,
            'duplicate_count' => 1,
            'missing_from_bank_count' => 1,
        ]);
        $this->assertDatabaseHas('bank_reconciliation_items', [
            'bank_reconciliation_id' => $reconciliation->id,
            'reference_no' => 'REF-MATCH-001',
            'status' => 'matched',
            'statement_amount' => '250000.00',
            'internal_amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bank_reconciliation_items', [
            'bank_reconciliation_id' => $reconciliation->id,
            'reference_no' => 'REF-MISMATCH-002',
            'status' => 'amount_mismatch',
            'statement_amount' => '100.00',
            'internal_amount' => '250000.00',
        ]);
        $this->assertDatabaseHas('bank_reconciliation_items', [
            'bank_reconciliation_id' => $reconciliation->id,
            'reference_no' => 'REF-UNKNOWN-003',
            'status' => 'not_found',
        ]);
        $this->assertDatabaseHas('bank_reconciliation_items', [
            'bank_reconciliation_id' => $reconciliation->id,
            'reference_no' => 'REF-MATCH-001',
            'status' => 'duplicate',
        ]);
        $this->assertDatabaseHas('bank_reconciliation_items', [
            'bank_reconciliation_id' => $reconciliation->id,
            'reference_no' => 'REF-MISSING-004',
            'status' => 'missing_from_bank',
        ]);

        $this->get(route('reconciliations.show', $reconciliation))
            ->assertOk()
            ->assertSee('Hasil Rekonsiliasi #'.$reconciliation->id)
            ->assertSee('Nominal berbeda')
            ->assertSee('Tidak ditemukan di sistem')
            ->assertSee('Duplikat di laporan')
            ->assertSee('Tidak ada di laporan bank');
    }

    public function test_csv_with_invalid_header_is_rejected_without_creating_reconciliation(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $bank = Bank::factory()->create();

        $this->actingAs($user)
            ->from(route('reconciliations.index'))
            ->post(route('reconciliations.store'), [
                'bank_id' => $bank->id,
                'period_from' => '2026-10-14',
                'period_to' => '2026-10-14',
                'statement_file' => File::createWithContent(
                    'invalid.csv',
                    "reference,amount,paid_at\nREF-001,250000.00,2026-10-14T10:00:00+07:00\n",
                ),
            ])
            ->assertRedirect(route('reconciliations.index'))
            ->assertSessionHasErrors('statement_file');

        $this->assertDatabaseCount('bank_reconciliations', 0);
        $this->assertDatabaseCount('bank_reconciliation_items', 0);
    }

    public function test_guest_and_non_super_admin_cannot_access_reconciliation(): void
    {
        $this->get(route('reconciliations.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('reconciliations.index'))
            ->assertForbidden();
    }

    private function createTransaction(
        Bank $bank,
        string $reference,
        string $amount = '250000.00',
        string $paidAt = '2026-10-14 03:00:00',
    ): PaymentTransaction {
        $sequence = Invoice::query()->count() + 1;
        $branch = Branch::query()->create([
            'name' => 'Cabang Rekonsiliasi '.$sequence,
            'code' => 'REC'.$sequence,
            'va_fixed_prefix' => sprintf('%08d', 85765000 + $sequence),
            'va_dynamic_prefix' => sprintf('%08d', 85765900 + $sequence),
            'api_key' => hash('sha256', (string) $sequence),
            'is_active' => true,
        ]);
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-RECONCILIATION-'.$sequence,
            'source' => 'api',
            'bill_code' => 'BILL-RECONCILIATION-'.$sequence,
            'bill_name' => 'Tagihan Rekonsiliasi',
            'customer_name' => 'Pelanggan Rekonsiliasi',
            'amount' => $amount,
            'fee_amount' => '0.00',
            'total_amount' => $amount,
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);
        $virtualAccount = VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => sprintf('857659%010d', $sequence),
            'virtual_account_no' => sprintf('857659%010d', $sequence),
            'virtual_account_name' => 'Pelanggan Rekonsiliasi',
            'current_amount' => $amount,
            'status' => 'active',
        ]);

        return PaymentTransaction::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $virtualAccount->id,
            'paid_amount' => $amount,
            'payment_reference_no' => $reference,
            'paid_at' => $paidAt,
        ]);
    }
}
