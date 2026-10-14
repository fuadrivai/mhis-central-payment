<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankReconciliationRequest;
use App\Models\BankReconciliation;
use App\Services\Contracts\BankReconciliationServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationServiceInterface $reconciliations) {}

    public function index(): View
    {
        return view('reconciliations.index', [
            'banks' => $this->reconciliations->activeBanks(),
            'reconciliations' => $this->reconciliations->paginateRuns(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['reference_no', 'amount', 'paid_at']);
            fclose($stream);
        }, 'bank-reconciliation-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreBankReconciliationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $reconciliation = $this->reconciliations->reconcile(
            (int) $validated['bank_id'],
            (int) $request->user()->id,
            $validated['period_from'],
            $validated['period_to'],
            $validated['statement_file'],
        );

        return redirect()
            ->route('reconciliations.show', $reconciliation)
            ->with('status', 'Laporan berhasil direkonsiliasi.');
    }

    public function show(BankReconciliation $reconciliation): View
    {
        return view('reconciliations.show', [
            'reconciliation' => $this->reconciliations->detail($reconciliation),
        ]);
    }
}
