<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceIndexRequest;
use App\Http\Requests\StoreDashboardInvoiceRequest;
use App\Models\Invoice;
use App\Services\Contracts\InvoiceServiceInterface;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoiceService,
        private readonly VirtualAccountServiceInterface $virtualAccountService,
    ) {}

    public function index(InvoiceIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('invoices.index', [
            'filters' => $filters,
            'invoices' => $this->invoiceService->paginate($filters),
        ]);
    }

    public function create(): View
    {
        return view('invoices.create', [
            'branches' => $this->virtualAccountService->activeBranches(),
            'banks' => $this->virtualAccountService->activeBanks(),
            'externalId' => $this->generateExternalId(),
        ]);
    }

    public function store(StoreDashboardInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->invoiceService->create([
            'branch_id' => (int) $data['branch_id'],
            'bank_id' => (int) $data['bank_id'],
            'source' => 'dashboard',
            'bill_code' => $data['external_id'],
            'bill_name' => $data['description'],
            'amount' => $data['amount'],
            'fee_amount' => 0,
            'expired_at' => str_replace('T', ' ', $data['expired_at']),
        ]);

        [$key, $message] = match ($result['outcome']) {
            VirtualAccountServiceInterface::OUTCOME_ACTIVE => [
                'status',
                'Invoice dan Dynamic VA berhasil dibuat.',
            ],
            VirtualAccountServiceInterface::OUTCOME_REJECTED => [
                'error',
                'Invoice dibuat, tetapi bank menolak pembuatan VA.',
            ],
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => [
                'error',
                'Invoice dibuat, tetapi backend bank mengalami kendala. Status VA perlu diverifikasi.',
            ],
            default => [
                'error',
                'Invoice dibuat, tetapi hasil pembuatan VA belum dapat dipastikan. Status VA perlu diverifikasi.',
            ],
        };

        return redirect()
            ->route('invoices.show', $result['invoice'])
            ->with($key, $message);
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

    private function generateExternalId(): string
    {
        do {
            $externalId = 'mutiar-'.Str::lower(Str::random(16));
        } while (Invoice::query()
            ->where('source', 'dashboard')
            ->where('bill_code', $externalId)
            ->exists());

        return $externalId;
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
