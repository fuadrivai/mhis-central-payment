<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceIndexRequest;
use App\Models\Invoice;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceServiceInterface $invoiceService) {}

    public function index(InvoiceIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('invoices.index', [
            'filters' => $filters,
            'invoices' => $this->invoiceService->paginate($filters),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice = $this->invoiceService->detail($invoice);
        $paymentUrl = null;

        if (
            $invoice->status === 'pending'
            && $invoice->expired_at?->isFuture()
            && $invoice->virtualAccount?->status === 'active'
        ) {
            $paymentUrl = URL::temporarySignedRoute(
                'payment.show',
                $invoice->expired_at,
                ['invoice' => $invoice],
            );
        }

        return view('invoices.show', [
            'invoice' => $invoice,
            'paymentUrl' => $paymentUrl,
        ]);
    }

    public function payment(Invoice $invoice): View
    {
        $invoice = $this->invoiceService->paymentPage($invoice);
        $canPay = $invoice->status === 'pending'
            && $invoice->expired_at?->isFuture()
            && $invoice->virtualAccount?->status === 'active';

        return view('invoices.payment', [
            'invoice' => $invoice,
            'canPay' => $canPay,
        ]);
    }

    public function voidInvoice(Invoice $invoice): RedirectResponse
    {
        $outcome = $this->invoiceService->voidInvoice($invoice);

        [$key, $message] = match ($outcome) {
            VirtualAccountServiceInterface::OUTCOME_VOIDED => ['status', 'VA berhasil dinonaktifkan di bank. Jika invoice masih pending, statusnya telah diubah menjadi expired.'],
            VirtualAccountServiceInterface::OUTCOME_REJECTED => ['error', 'Bank menolak permintaan void. Invoice tidak ditandai expired.'],
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => ['error', 'Bank mengalami kendala saat memproses void. Status VA perlu diverifikasi sebelum mencoba kembali.'],
            default => ['error', 'Hasil permintaan void belum dapat dipastikan. Status VA perlu diverifikasi sebelum mencoba kembali.'],
        };

        return redirect()->route('invoices.show', $invoice)->with($key, $message);
    }
}
