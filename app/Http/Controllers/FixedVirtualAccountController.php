<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedVirtualAccountRequest;
use App\Services\Contracts\FixedVirtualAccountServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FixedVirtualAccountController extends Controller
{
    public function __construct(private readonly FixedVirtualAccountServiceInterface $fixedVirtualAccountService) {}

    public function index(): View
    {
        return view('virtual-accounts.fixed.index', [
            'virtualAccounts' => $this->fixedVirtualAccountService->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('virtual-accounts.fixed.create', [
            'branches' => $this->fixedVirtualAccountService->activeBranches(),
        ]);
    }

    public function store(StoreFixedVirtualAccountRequest $request): RedirectResponse
    {
        $result = $this->fixedVirtualAccountService->create($request->validated());

        $redirect = redirect()->route('virtual-accounts.fixed.index');

        return match ($result['outcome']) {
            FixedVirtualAccountServiceInterface::OUTCOME_ACTIVE => $redirect
                ->with('status', 'Fixed VA berhasil dibuat dan terdaftar di backend Mandiri H2H.'),
            FixedVirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => $redirect
                ->with('error', 'Backend H2H mengalami kendala. Status VA perlu diverifikasi sebelum mencoba kembali.'),
            FixedVirtualAccountServiceInterface::OUTCOME_REJECTED => $redirect
                ->with('error', 'Backend H2H menolak pembuatan Fixed VA. Periksa log integrasi untuk detailnya.'),
            default => $redirect
                ->with('error', 'Tidak dapat memastikan hasil pembuatan VA dari H2H. VA ditandai perlu verifikasi; jangan kirim ulang sebelum statusnya diperiksa.'),
        };
    }
}
