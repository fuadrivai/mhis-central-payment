<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MandiriPaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_API_KEY = 'test-mandiri-callback-api-key';

    public function test_valid_api_key_callback_records_payment_and_marks_invoice_paid(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload())
            ->assertOk()
            ->assertJsonPath('outcome', 'processed');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'paid_at' => '2026-10-12 08:00:00',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'branch_id' => $invoice->branch_id,
            'bank_id' => $invoice->virtualAccount->bank_id,
            'invoice_id' => $invoice->id,
            'virtual_account_id' => $invoice->virtualAccount->id,
            'paid_amount' => '150000.00',
            'payment_reference_no' => 'REF-20261012-001',
            'payment_request_id' => 'REQ-123',
            'paid_at' => '2026-10-12 08:00:00',
        ]);
        $this->assertSame(1, PaymentTransaction::query()->count());
    }

    public function test_callback_with_additional_mandiri_fields_records_payment(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();
        $payload = $this->payload();
        $payload['data'] += [
            'billName' => 'Lomba Seamo Primary',
            'partnerServiceId' => '85765',
            'channelCode' => 'MOBILE',
            'hashedSourceAccountNo' => 'hashed-account-reference',
            'trxDateTime' => '2026-10-05T16:34:16+07:00',
        ];

        $this->postCallback($payload)
            ->assertOk()
            ->assertJsonPath('outcome', 'processed');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseCount('payment_transactions', 1);

        $transaction = PaymentTransaction::query()->firstOrFail();

        $this->assertSame('Lomba Seamo Primary', $transaction->raw_payload['data']['billName']);
        $this->assertSame('85765', $transaction->raw_payload['data']['partnerServiceId']);
        $this->assertSame('MOBILE', $transaction->raw_payload['data']['channelCode']);
        $this->assertSame('hashed-account-reference', $transaction->raw_payload['data']['hashedSourceAccountNo']);
        $this->assertSame('2026-10-05T16:34:16+07:00', $transaction->raw_payload['data']['trxDateTime']);
    }

    public function test_callback_with_json_encoded_data_records_payment(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();
        $payload = $this->payload();
        $payload['data'] = json_encode($payload['data'], JSON_THROW_ON_ERROR);

        $this->postCallback($payload)
            ->assertOk()
            ->assertJsonPath('outcome', 'processed');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_duplicate_api_key_callback_is_acknowledged_without_adding_a_second_transaction(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();
        $payload = $this->payload();

        $this->postCallback($payload)->assertOk()->assertJsonPath('outcome', 'processed');
        $this->postCallback($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');

        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
    }

    public function test_callback_with_invalid_api_key_is_rejected_without_processing_payment(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload(), 'incorrect-callback-api-key')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid callback API key.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_callback_without_an_api_key_is_rejected_without_processing_payment(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload(), null)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid callback API key.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_callback_is_unavailable_when_its_api_key_is_not_configured(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();
        Bank::query()->where('code', 'mandiri')->update(['callback_api_key_hash' => null]);

        $this->postCallback($this->payload())
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Callback API key is not configured.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_callback_with_invalid_payload_is_rejected(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload(['status' => 'PENDING']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.status');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_paid_callback_with_inactive_virtual_account_status_is_processed(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload(['status' => 'INACTIVE']))
            ->assertOk()
            ->assertJsonPath('outcome', 'processed');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'paid_at' => '2026-10-12 08:00:00',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'invoice_id' => $invoice->id,
            'payment_reference_no' => 'REF-20261012-001',
        ]);
    }

    public function test_callback_with_unknown_virtual_account_is_not_processed(): void
    {
        $this->configureCallbackApiKey();

        $this->postCallback($this->payload(['virtualAccountNo' => '8576500000000000']))
            ->assertNotFound()
            ->assertJsonPath('outcome', 'not_found');

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_callback_with_amount_or_bill_code_mismatch_does_not_mark_invoice_paid(): void
    {
        $invoice = $this->createInvoiceWithVirtualAccount();

        $this->postCallback($this->payload(['paidAmount' => '149999.00']))
            ->assertUnprocessable()
            ->assertJsonPath('outcome', 'invalid');

        $this->postCallback($this->payload(['billCode' => 'WRONG-BILL-CODE']))
            ->assertUnprocessable()
            ->assertJsonPath('outcome', 'invalid');

        $this->postCallback($this->payload(['customerNo' => '089999999999']))
            ->assertUnprocessable()
            ->assertJsonPath('outcome', 'invalid');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'pending']);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_callback_with_reused_reference_for_another_invoice_returns_conflict(): void
    {
        $firstInvoice = $this->createInvoiceWithVirtualAccount();
        $this->postCallback($this->payload())->assertOk();

        $secondInvoice = $this->createInvoiceWithVirtualAccount([
            'invoice_number' => 'INV-CALLBACK-SECOND',
            'bill_code' => 'CALLBACK-BILL-SECOND',
        ]);
        $secondPayload = $this->payload([
            'virtualAccountNo' => $secondInvoice->virtualAccount->virtual_account_no,
            'customerNo' => $secondInvoice->virtualAccount->customer_no,
            'billCode' => $secondInvoice->bill_code,
        ]);

        $this->postCallback($secondPayload)
            ->assertConflict()
            ->assertJsonPath('outcome', 'conflict');

        $this->assertDatabaseHas('invoices', ['id' => $firstInvoice->id, 'status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['id' => $secondInvoice->id, 'status' => 'pending']);
        $this->assertSame(1, PaymentTransaction::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoiceWithVirtualAccount(array $overrides = []): Invoice
    {
        $sequence = Branch::query()->count() + 1;
        $branch = Branch::query()->create([
            'name' => 'Callback Branch',
            'code' => 'CBK'.$sequence,
            'va_fixed_prefix' => sprintf('857650%02d', $sequence),
            'va_dynamic_prefix' => sprintf('857659%02d', $sequence),
            'api_key' => hash('sha256', (string) $sequence),
            'is_active' => true,
        ]);
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();
        $this->configureCallbackApiKey($bank);
        $vaNumber = $sequence === 1
            ? '85765081234567890'
            : '857650812345678'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
        $invoice = Invoice::query()->create($overrides + [
            'branch_id' => $branch->id,
            'va_type' => 'dynamic',
            'invoice_number' => 'INV-CALLBACK-001',
            'source' => 'api',
            'bill_code' => 'CALLBACK-BILL-001',
            'bill_name' => 'Callback Test Invoice',
            'customer_name' => 'Budi Santoso',
            'email' => 'budi@example.com',
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
            'virtual_account_no' => $vaNumber,
            'virtual_account_name' => 'Budi Santoso',
            'current_amount' => '150000.00',
            'expired_at' => $invoice->expired_at,
            'status' => 'active',
        ]);

        return $invoice->load('virtualAccount');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array{event: string, data: array<string, string>, timestamp: string}
     */
    private function payload(array $overrides = []): array
    {
        return [
            'event' => 'va.paid',
            'data' => $overrides + [
                'virtualAccountNo' => '85765081234567890',
                'customerNo' => '081234567890',
                'virtualAccountName' => 'Budi Santoso',
                'billCode' => 'CALLBACK-BILL-001',
                'totalAmount' => '150000.00',
                'paidAmount' => '150000.00',
                'paymentRequestId' => 'REQ-123',
                'referenceNo' => 'REF-20261012-001',
                'status' => 'PAID',
            ],
            'timestamp' => '2026-10-12T08:00:00Z',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postCallback(array $payload, ?string $apiKey = self::CALLBACK_API_KEY): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($apiKey !== null) {
            $server['HTTP_X_API_KEY'] = $apiKey;
        }

        return $this->call('POST', route('api.v1.callbacks.mandiri.virtual-account-paid'), server: $server, content: $body);
    }

    private function configureCallbackApiKey(?Bank $bank = null): void
    {
        $bank ??= Bank::query()->where('code', 'mandiri')->firstOrFail();
        $bank->forceFill(['callback_api_key_hash' => hash('sha256', self::CALLBACK_API_KEY)])->save();
    }
}
