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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_all_six_reports(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        foreach (['summary', 'students', 'unpaid', 'reconciliations', 'branches', 'issues'] as $report) {
            $this->actingAs($user)
                ->get(route('reports.index', ['report' => $report]))
                ->assertOk()
                ->assertSee('Laporan Pembayaran');
        }
    }

    public function test_student_report_filters_by_nis_and_exports_excel_safe_csv(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $transaction = $this->createTransaction(studentName: '=HYPERLINK("https://example.com")');
        $otherTransaction = $this->createTransaction(
            reference: 'REF-REPORT-OTHER',
            invoiceNumber: 'INV-REPORT-OTHER',
            billCode: 'BILL-REPORT-OTHER',
            virtualAccountNumber: '8576501098765432',
            nis: '09999999',
        );

        $this->actingAs($user)
            ->get(route('reports.index', [
                'report' => 'students',
                'search' => '01125014',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($transaction->payment_reference_no)
            ->assertDontSee($otherTransaction->payment_reference_no);

        $response = $this->actingAs($user)
            ->get(route('reports.export', [
                'report' => 'students',
                'search' => '01125014',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($transaction->payment_reference_no, $csv);
        $this->assertStringNotContainsString($otherTransaction->payment_reference_no, $csv);
    }

    public function test_report_requires_permission_and_rejects_invalid_filters(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($superAdmin)
            ->get(route('reports.index', ['report' => 'not-a-report']))
            ->assertSessionHasErrors('report');
    }

    public function test_unpaid_reconciliation_and_issue_reports_return_matching_records(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $transaction = $this->createTransaction();
        $invoice = $transaction->invoice;

        Invoice::query()->create([
            'branch_id' => $invoice->branch_id,
            'student_id' => $invoice->student_id,
            'va_type' => 'fixed',
            'invoice_number' => 'INV-REPORT-UNPAID',
            'source' => 'api',
            'bill_code' => 'BILL-REPORT-UNPAID',
            'bill_name' => 'Tagihan Belum Dibayar',
            'customer_name' => 'Siswa Laporan',
            'amount' => '100000.00',
            'fee_amount' => '0.00',
            'total_amount' => '100000.00',
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);

        $reconciliationId = DB::table('bank_reconciliations')->insertGetId([
            'bank_id' => $transaction->bank_id,
            'original_filename' => 'report.csv',
            'period_from' => today()->toDateString(),
            'period_to' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bank_reconciliation_items')->insert([
            'bank_reconciliation_id' => $reconciliationId,
            'payment_transaction_id' => $transaction->id,
            'reference_no' => 'REF-REPORT-RECON',
            'statement_amount' => '250000.00',
            'internal_amount' => '250000.00',
            'statement_paid_at' => now(),
            'status' => 'matched',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('h2h_api_logs')->insert([
            'branch_id' => $transaction->branch_id,
            'bank_id' => $transaction->bank_id,
            'invoice_id' => $transaction->invoice_id,
            'virtual_account_id' => $transaction->virtual_account_id,
            'endpoint' => '/api/v1/va',
            'http_method' => 'POST',
            'response_status' => 503,
            'duration_ms' => 100,
            'error_message' => 'Bank unavailable',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $filters = [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ];

        $this->actingAs($user)
            ->get(route('reports.index', $filters + ['report' => 'unpaid']))
            ->assertOk()
            ->assertSee('INV-REPORT-UNPAID');

        $this->actingAs($user)
            ->get(route('reports.index', $filters + ['report' => 'reconciliations']))
            ->assertOk()
            ->assertSee('REF-REPORT-RECON');

        $this->actingAs($user)
            ->get(route('reports.index', $filters + ['report' => 'issues', 'status' => '503']))
            ->assertOk()
            ->assertSee('Bank unavailable')
            ->assertSee('503');
    }

    private function createTransaction(
        string $reference = 'REF-REPORT-001',
        string $invoiceNumber = 'INV-REPORT-001',
        string $billCode = 'BILL-REPORT-001',
        string $virtualAccountNumber = '8576501012345678',
        string $nis = '01125014',
        string $studentName = 'Siswa Laporan',
    ): PaymentTransaction {
        $sequence = Branch::query()->count() + 1;
        $branch = Branch::query()->create([
            'name' => 'Cabang Laporan',
            'code' => 'RPT'.$sequence,
            'va_fixed_prefix' => sprintf('857650%02d', $sequence),
            'va_dynamic_prefix' => sprintf('857659%02d', $sequence),
            'api_key' => hash('sha256', (string) $sequence),
            'is_active' => true,
        ]);
        $bank = Bank::factory()->create(['name' => 'Bank Laporan']);
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nis' => $nis,
            'nisn' => 'NISN-'.$nis,
            'name' => $studentName,
            'fixed_va_number' => 'FIXED-'.$sequence,
            'status' => 'active',
        ]);
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'student_id' => $student->id,
            'va_type' => 'fixed',
            'invoice_number' => $invoiceNumber,
            'source' => 'api',
            'bill_code' => $billCode,
            'bill_name' => 'Tagihan Laporan',
            'customer_name' => $studentName,
            'email' => 'report@example.com',
            'amount' => '250000.00',
            'fee_amount' => '0.00',
            'total_amount' => '250000.00',
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $virtualAccount = VirtualAccount::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'student_id' => $student->id,
            'invoice_id' => $invoice->id,
            'va_type' => 'fixed',
            'partner_service_id' => '85765',
            'customer_no' => $virtualAccountNumber,
            'virtual_account_no' => $virtualAccountNumber,
            'virtual_account_name' => $studentName,
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
            'paid_at' => now(),
        ]);
    }
}
