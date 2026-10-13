<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->withCount(['invoices', 'students', 'virtualAccounts'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('settings.branches.index', ['branches' => $branches]);
    }

    public function create(): View
    {
        return view('settings.branches.form', [
            'branch' => new Branch,
            'hasInvoices' => false,
            'hasVirtualAccounts' => false,
            'hasFinancialRecords' => false,
        ]);
    }

    public function store(SaveBranchRequest $request): RedirectResponse
    {
        $branch = new Branch;
        $branch->fill($request->validated());
        $branch->generateDynamicApiKey();
        $branch->save();

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'Cabang berhasil dibuat. Simpan API key sekarang karena key hanya ditampilkan satu kali.')
            ->with('branch_api_key', $branch->api_key)
            ->with('branch_api_key_name', $branch->name);
    }

    public function edit(Branch $branch): View
    {
        $hasInvoices = $branch->invoices()->exists();
        $hasVirtualAccounts = $branch->virtualAccounts()->exists();

        return view('settings.branches.form', [
            'branch' => $branch,
            'hasInvoices' => $hasInvoices,
            'hasVirtualAccounts' => $hasVirtualAccounts,
            'hasFinancialRecords' => $hasInvoices || $hasVirtualAccounts,
        ]);
    }

    public function update(SaveBranchRequest $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validated();
        $hasInvoices = $branch->invoices()->exists();
        $hasVirtualAccounts = $branch->virtualAccounts()->exists();

        if ($hasInvoices && $branch->code !== $validated['code']) {
            throw ValidationException::withMessages([
                'code' => 'Kode cabang tidak dapat diubah setelah cabang memiliki invoice.',
            ]);
        }

        if (
            $hasVirtualAccounts
            && (
                $branch->va_fixed_prefix !== $validated['va_fixed_prefix']
                || $branch->va_dynamic_prefix !== $validated['va_dynamic_prefix']
            )
        ) {
            throw ValidationException::withMessages([
                'va_fixed_prefix' => 'Prefix VA tidak dapat diubah setelah cabang memiliki Virtual Account.',
                'va_dynamic_prefix' => 'Prefix VA tidak dapat diubah setelah cabang memiliki Virtual Account.',
            ]);
        }

        $branch->fill($validated)->save();

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'Pengaturan cabang berhasil diperbarui.');
    }

    public function rotateApiKey(Branch $branch): RedirectResponse
    {
        $branch->api_key = bin2hex(random_bytes(32));
        $branch->save();

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'API key cabang berhasil dirotasi. Key lama sudah tidak berlaku; simpan key baru sekarang.')
            ->with('branch_api_key', $branch->api_key)
            ->with('branch_api_key_name', $branch->name);
    }
}
