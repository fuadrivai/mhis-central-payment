<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Contracts\BranchWebhookDeliveryServiceInterface;
use App\Services\Contracts\PaymentTransactionServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentTransactionController extends Controller
{
    public function __construct(
        private readonly PaymentTransactionServiceInterface $paymentTransactions,
        private readonly BranchWebhookDeliveryServiceInterface $branchWebhookDeliveries,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        return view('transactions.index', [
            'transactions' => $this->paymentTransactions->paginate($search),
            'search' => $search,
        ]);
    }

    public function show(PaymentTransaction $paymentTransaction): View
    {
        return view('transactions.show', [
            'transaction' => $this->paymentTransactions->detail($paymentTransaction),
        ]);
    }

    public function retryBranchWebhook(PaymentTransaction $paymentTransaction): RedirectResponse
    {
        $delivery = $this->branchWebhookDeliveries->retry($paymentTransaction);

        if ($delivery->status === 'skipped') {
            return back()->with('error', 'URL callback cabang belum dikonfigurasi.');
        }

        return back()->with('status', 'Callback ke cabang telah dimasukkan ke antrean.');
    }
}
