<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveBranchRequest;
use App\Models\Branch;
use App\Services\Contracts\BranchServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(private readonly BranchServiceInterface $branchService) {}

    public function index(): View
    {
        return view('settings.branches.index', ['branches' => $this->branchService->paginate()]);
    }

    public function create(): View
    {
        $branch = new Branch;

        return view('settings.branches.form', [
            'branch' => $branch,
            ...$this->branchService->financialState($branch),
        ]);
    }

    public function store(SaveBranchRequest $request): RedirectResponse
    {
        $branch = $this->branchService->create($request->validated());

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'Cabang berhasil dibuat. Simpan API key sekarang karena key hanya ditampilkan satu kali.')
            ->with('branch_api_key', $branch->api_key)
            ->with('branch_api_key_name', $branch->name);
    }

    public function edit(Branch $branch): View
    {
        return view('settings.branches.form', [
            'branch' => $branch,
            ...$this->branchService->financialState($branch),
        ]);
    }

    public function update(SaveBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->branchService->update($branch, $request->validated());

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'Pengaturan cabang berhasil diperbarui.');
    }

    public function rotateApiKey(Branch $branch): RedirectResponse
    {
        $branch = $this->branchService->rotateApiKey($branch);

        return redirect()
            ->route('settings.branches.index')
            ->with('status', 'API key cabang berhasil dirotasi. Key lama sudah tidak berlaku; simpan key baru sekarang.')
            ->with('branch_api_key', $branch->api_key)
            ->with('branch_api_key_name', $branch->name);
    }
}
