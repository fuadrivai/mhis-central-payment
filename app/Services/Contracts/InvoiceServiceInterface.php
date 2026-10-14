<?php

namespace App\Services\Contracts;

use App\Models\Invoice;
use App\Models\VirtualAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceServiceInterface
{
    /**
     * Invoices with their VA and payment transaction results, newest first.
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Load everything the invoice detail page needs.
     */
    public function detail(Invoice $invoice): Invoice;

    /**
     * Load only the invoice and active payment details displayed on the signed payer page.
     */
    public function paymentPage(Invoice $invoice): Invoice;

    /**
     * Void an invoice's active dynamic VA through its bank.
     *
     * @return VirtualAccountServiceInterface::OUTCOME_*
     */
    public function voidInvoice(Invoice $invoice): string;

    /**
     * Create an invoice together with a random, unique dynamic VA at the given bank.
     *
     * @param  array<string, mixed>  $data
     * @return array{outcome: string, invoice: Invoice, virtualAccount: VirtualAccount}
     */
    public function create(array $data): array;
}
