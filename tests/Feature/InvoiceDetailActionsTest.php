<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\H2hApiLog;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvoiceDetailActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invoice_with_active_va_has_signed_payment_link_and_pdf_action(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk()
            ->assertSee('Copy Payment Link')
            ->assertSee('window.print()', false)
            ->assertSee('Void')
            ->assertSee(route('invoices.void', $invoice), false)
            ->assertSee('data-bs-toggle="modal"', false)
            ->assertSee('data-bs-target="#void-invoice-modal"', false)
            ->assertSee('Konfirmasi Void Invoice')
            ->assertSee('Ya, Void Invoice')
            ->assertSee('Address has been copied')
            ->assertSee("document.execCommand('copy')", false)
            ->assertDontSee('window.prompt', false);

        $paymentUrl = $response->viewData('paymentUrl');
        $this->assertNotNull($paymentUrl);

        $this->get($paymentUrl)
            ->assertOk()
            ->assertSee($invoice->bill_code)
            ->assertSee('9988776655443322')
            ->assertDontSee('payer-private@example.com');
    }

    public function test_payment_page_rejects_unsigned_requests(): void
    {
        $invoice = $this->createInvoiceWithVa();

        $this->get(route('payment.show', $invoice))->assertForbidden();
    }

    public function test_invoice_without_active_va_has_no_payment_link(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa(['status' => 'failed']);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk()
            ->assertSee('Copy Payment Link')
            ->assertSee('disabled');

        $this->assertNull($response->viewData('paymentUrl'));
    }

    public function test_non_pending_invoice_does_not_show_the_void_action(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa(['status' => 'paid']);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('invoices.void', $invoice), false)
            ->assertDontSee('<button type="submit" class="btn btn-outline-danger">Void</button>', false);
    }

    public function test_invoice_displays_transactions_as_cards_with_links_to_transaction_details(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $transaction = $this->createPaymentTransaction($invoice);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Detail Transaksi')
            ->assertSee('Waktu Bayar')
            ->assertSee('Bank Uji')
            ->assertSee('Kanal VA')
            ->assertSee('REF-TRANSACTION-001')
            ->assertSee('REQ-TRANSACTION-001')
            ->assertSee(route('transactions.show', $transaction), false)
            ->assertDontSee('<table', false);
    }

    public function test_super_admin_can_view_all_payment_transaction_details(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $transaction = $this->createPaymentTransaction($invoice);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Detail Transaksi #'.$transaction->id)
            ->assertSee('REF-TRANSACTION-001')
            ->assertSee('REQ-TRANSACTION-001')
            ->assertSee('Bank Uji')
            ->assertSee('Cabang Uji')
            ->assertSee('9988776655443322')
            ->assertSee($invoice->invoice_number)
            ->assertSee('Callback ke Cabang')
            ->assertSee('Kirim Ulang Callback')
            ->assertSee('va.paid')
            ->assertSee('250000.00');
    }

    public function test_guest_cannot_view_payment_transaction_details(): void
    {
        $invoice = $this->createInvoiceWithVa();
        $transaction = $this->createPaymentTransaction($invoice);

        $this->get(route('transactions.show', $transaction))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_view_payment_transaction_details(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $transaction = $this->createPaymentTransaction($invoice);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertForbidden();
    }

    public function test_paid_invoice_payment_page_does_not_expose_its_va(): void
    {
        $invoice = $this->createInvoiceWithVa();
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        $url = URL::temporarySignedRoute(
            'payment.show',
            now()->addHour(),
            ['invoice' => $invoice],
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('Pembayaran tidak tersedia')
            ->assertDontSee('9988776655443322');
    }

    public function test_super_admin_can_void_an_invoice_and_deactivate_its_va_at_the_bank(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['status' => 'success'], 200),
        ]);
        $this->configureH2h();
        $this->travelTo('2026-10-15 12:34:56');
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $virtualAccount = $invoice->virtualAccount;

        $this->actingAs($user)
            ->post(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', 'VA berhasil dinonaktifkan di bank. Jika invoice masih pending, statusnya telah diubah menjadi expired.');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $virtualAccount->id,
            'status' => 'inactive',
            'expired_at' => '2026-10-15 12:34:56',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $virtualAccount->id,
            'endpoint' => '/api/internal/va/update',
            'http_method' => 'PUT',
            'response_status' => 200,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://h2h.test/api/internal/va/update'
            && $request->hasHeader('X-API-KEY', 'test-h2h-key')
            && $request->data() === [
                'virtualAccountNo' => '9988776655443322',
                'totalAmount' => '250000.00',
                'expiredAt' => '2026-10-15 12:34:56',
                'status' => 'INACTIVE',
            ]);
    }

    public function test_bank_rejection_keeps_the_invoice_and_active_va_unchanged(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['message' => 'rejected'], 422),
        ]);
        $this->configureH2h();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $virtualAccount = $invoice->virtualAccount;

        $this->actingAs($user)
            ->post(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', 'Bank menolak permintaan void. Invoice tidak ditandai expired.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseHas('virtual_accounts', ['id' => $virtualAccount->id, 'status' => 'active']);
        $this->assertDatabaseHas('h2h_api_logs', [
            'invoice_id' => $invoice->id,
            'http_method' => 'PUT',
            'response_status' => 422,
        ]);
    }

    public function test_bank_server_error_marks_the_va_for_verification_without_expiring_the_invoice(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::response(['message' => 'unavailable'], 500),
        ]);
        $this->configureH2h();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();

        $this->actingAs($user)
            ->post(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', 'Bank mengalami kendala saat memproses void. Status VA perlu diverifikasi sebelum mencoba kembali.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseHas('virtual_accounts', [
            'invoice_id' => $invoice->id,
            'status' => 'verification_required',
        ]);
        $this->assertDatabaseHas('h2h_api_logs', [
            'invoice_id' => $invoice->id,
            'http_method' => 'PUT',
            'response_status' => 500,
        ]);
    }

    public function test_connection_failure_keeps_the_va_marked_for_verification(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://h2h.test/api/internal/va/update' => Http::failedConnection(),
        ]);
        $this->configureH2h();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();

        $this->actingAs($user)
            ->post(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('error', 'Hasil permintaan void belum dapat dipastikan. Status VA perlu diverifikasi sebelum mencoba kembali.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseHas('virtual_accounts', [
            'invoice_id' => $invoice->id,
            'status' => 'verification_required',
        ]);
        $log = H2hApiLog::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('PUT', $log->http_method);
        $this->assertNotEmpty($log->error_message);
    }

    public function test_guest_cannot_void_an_invoice(): void
    {
        $invoice = $this->createInvoiceWithVa();

        $this->post(route('invoices.void', $invoice))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseHas('virtual_accounts', ['invoice_id' => $invoice->id, 'status' => 'active']);
    }

    public function test_non_super_admin_cannot_void_an_invoice(): void
    {
        $user = User::factory()->create(['role' => 'finance_pusat', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();

        $this->actingAs($user)
            ->post(route('invoices.void', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseHas('virtual_accounts', ['invoice_id' => $invoice->id, 'status' => 'active']);
    }

    public function test_non_pending_invoice_cannot_be_voided(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $invoice = $this->createInvoiceWithVa();
        $invoice->update(['status' => 'paid']);

        $this->actingAs($user)
            ->from(route('invoices.show', $invoice))
            ->post(route('invoices.void', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertDatabaseHas('virtual_accounts', ['invoice_id' => $invoice->id, 'status' => 'active']);
    }

    private function configureH2h(): void
    {
        config([
            'services.mandiri_h2h.base_url' => 'https://h2h.test',
            'services.mandiri_h2h.api_key' => 'test-h2h-key',
            'services.mandiri_h2h.partner_service_id' => '85765',
        ]);
    }

    private function createInvoiceWithVa(array $virtualAccountOverrides = []): Invoice
    {
        $branch = Branch::query()->create([
            'name' => 'Bintaro',
            'code' => 'BTR',
            'va_fixed_prefix' => '85765010',
            'va_dynamic_prefix' => '85765991',
            'api_key' => 'branch-api-key-1234567890',
            'is_active' => true,
        ]);
        $bank = Bank::factory()->create();
        $invoice = Invoice::query()->create([
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-PAYMENT-ACTIONS-01',
            'source' => 'api',
            'bill_code' => 'PAYMENT-ACTIONS-01',
            'bill_name' => 'Tagihan Uji',
            'customer_name' => 'Payer Uji',
            'email' => 'payer-private@example.com',
            'amount' => '250000.00',
            'fee_amount' => '0.00',
            'total_amount' => '250000.00',
            'status' => 'pending',
            'expired_at' => now()->addDay(),
        ]);
        VirtualAccount::query()->create($virtualAccountOverrides + [
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'va_type' => 'dynamic',
            'partner_service_id' => '85765',
            'customer_no' => '9988776655443322',
            'virtual_account_no' => '9988776655443322',
            'virtual_account_name' => 'Payer Uji',
            'current_amount' => '250000.00',
            'expired_at' => $invoice->expired_at,
            'status' => 'active',
        ]);

        return $invoice;
    }

    private function createPaymentTransaction(Invoice $invoice): PaymentTransaction
    {
        $virtualAccount = $invoice->virtualAccount()->firstOrFail();
        $bank = $virtualAccount->bank()->firstOrFail();
        $branch = $invoice->branch()->firstOrFail();
        $branch->update(['name' => 'Cabang Uji']);
        $bank->update(['name' => 'Bank Uji']);

        return PaymentTransaction::query()->create([
            'branch_id' => $branch->id,
            'bank_id' => $bank->id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $virtualAccount->id,
            'paid_amount' => '250000.00',
            'payment_reference_no' => 'REF-TRANSACTION-001',
            'payment_request_id' => 'REQ-TRANSACTION-001',
            'channel' => 'Kanal VA',
            'paid_at' => '2026-10-14 11:30:00',
            'raw_payload' => [
                'event' => 'va.paid',
                'data' => ['paidAmount' => '250000.00'],
            ],
        ]);
    }
}
