<?php

namespace App\Http\Controllers;

use App\Enums\VirtualAccountType;
use App\Http\Requests\StoreBulkFixedVirtualAccountRequest;
use App\Http\Requests\StoreFixedVirtualAccountRequest;
use App\Http\Requests\UpdateFixedVirtualAccountRequest;
use App\Models\VirtualAccount;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FixedVirtualAccountController extends Controller
{
    public function __construct(private readonly VirtualAccountServiceInterface $virtualAccountService) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        return view('virtual-accounts.fixed.index', [
            'virtualAccounts' => $this->virtualAccountService->paginate(VirtualAccountType::Fixed, search: $search),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('virtual-accounts.fixed.create', [
            'branches' => $this->virtualAccountService->activeBranches(),
            'banks' => $this->virtualAccountService->activeBanks(),
        ]);
    }

    public function bulkCreate(): View
    {
        return view('virtual-accounts.fixed.bulk', [
            'branches' => $this->virtualAccountService->activeBranches(),
            'banks' => $this->virtualAccountService->activeBanks(),
        ]);
    }

    public function bulkTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['nis', 'nama_siswa', 'grade', 'email', 'tgl_expired_va']);
            fclose($stream);
        }, 'fixed-va-students-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreFixedVirtualAccountRequest $request): RedirectResponse
    {
        $result = $this->virtualAccountService->createFixed($request->validated());

        $redirect = redirect()->route('virtual-accounts.fixed.index');

        return match ($result['outcome']) {
            VirtualAccountServiceInterface::OUTCOME_ACTIVE => $redirect
                ->with('status', 'Fixed VA berhasil dibuat dan terdaftar di backend bank.'),
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => $redirect
                ->with('error', 'Backend H2H mengalami kendala. Status VA perlu diverifikasi sebelum mencoba kembali.'),
            VirtualAccountServiceInterface::OUTCOME_REJECTED => $redirect
                ->with('error', 'Backend H2H menolak pembuatan Fixed VA. Periksa log integrasi untuk detailnya.'),
            default => $redirect
                ->with('error', 'Tidak dapat memastikan hasil pembuatan VA dari H2H. VA ditandai perlu verifikasi; jangan kirim ulang sebelum statusnya diperiksa.'),
        };
    }

    public function edit(VirtualAccount $virtualAccount): View
    {
        return view('virtual-accounts.fixed.edit', [
            'virtualAccount' => $this->virtualAccountService->findEditableFixedVirtualAccount($virtualAccount),
        ]);
    }

    public function update(
        UpdateFixedVirtualAccountRequest $request,
        VirtualAccount $virtualAccount,
    ): RedirectResponse {
        $outcome = $this->virtualAccountService->updateFixed($virtualAccount, $request->validated());
        $redirect = redirect()->route('virtual-accounts.fixed.index');

        return match ($outcome) {
            VirtualAccountServiceInterface::OUTCOME_UPDATED => $redirect
                ->with('status', 'Data Fixed VA berhasil diperbarui di sistem dan backend bank.'),
            VirtualAccountServiceInterface::OUTCOME_REJECTED => $redirect
                ->with('error', 'Backend H2H menolak perubahan Fixed VA. Data lokal tidak diubah.'),
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR => $redirect
                ->with('error', 'Backend H2H mengalami kendala. Status VA perlu diverifikasi sebelum perubahan dicoba kembali.'),
            default => $redirect
                ->with('error', 'Tidak dapat memastikan hasil perubahan di H2H. Status VA perlu diverifikasi sebelum perubahan dicoba kembali.'),
        };
    }

    public function bulkStore(StoreBulkFixedVirtualAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $result = $this->virtualAccountService->bulkCreateFixed(
            (int) $validated['branch_id'],
            (int) $validated['bank_id'],
            $validated['student_file'],
        );
        $failedRows = array_values(array_filter(
            $result['results'],
            static fn (array $row): bool => $row['outcome'] !== VirtualAccountServiceInterface::OUTCOME_ACTIVE,
        ));

        return redirect()
            ->route('virtual-accounts.fixed.index')
            ->with('status', sprintf(
                'Import selesai: %d dari %d Fixed VA berhasil aktif di backend bank.',
                $result['created'],
                count($result['results']),
            ))
            ->with('bulk_fixed_va_failures', $failedRows);
    }
}
