<?php

namespace App\Services\Implementations;

use App\Models\Branch;
use App\Services\Contracts\BranchServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class BranchService implements BranchServiceInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Branch::query()
            ->withCount(['invoices', 'students', 'virtualAccounts'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function financialState(Branch $branch): array
    {
        if (! $branch->exists) {
            return ['hasInvoices' => false, 'hasVirtualAccounts' => false, 'hasFinancialRecords' => false];
        }

        $hasInvoices = $branch->invoices()->exists();
        $hasVirtualAccounts = $branch->virtualAccounts()->exists();

        return [
            'hasInvoices' => $hasInvoices,
            'hasVirtualAccounts' => $hasVirtualAccounts,
            'hasFinancialRecords' => $hasInvoices || $hasVirtualAccounts,
        ];
    }

    public function create(array $data): Branch
    {
        $branch = new Branch;
        $branch->fill($data);
        $branch->generateDynamicApiKey();
        $branch->save();

        return $branch;
    }

    public function update(Branch $branch, array $data): Branch
    {
        $state = $this->financialState($branch);

        if ($state['hasInvoices'] && $branch->code !== $data['code']) {
            throw ValidationException::withMessages([
                'code' => 'Kode cabang tidak dapat diubah setelah cabang memiliki invoice.',
            ]);
        }

        if (
            $state['hasVirtualAccounts']
            && (
                $branch->va_fixed_prefix !== $data['va_fixed_prefix']
                || $branch->va_dynamic_prefix !== $data['va_dynamic_prefix']
            )
        ) {
            throw ValidationException::withMessages([
                'va_fixed_prefix' => 'Prefix VA tidak dapat diubah setelah cabang memiliki Virtual Account.',
                'va_dynamic_prefix' => 'Prefix VA tidak dapat diubah setelah cabang memiliki Virtual Account.',
            ]);
        }

        $branch->fill($data)->save();

        return $branch;
    }

    public function rotateApiKey(Branch $branch): Branch
    {
        $branch->api_key = bin2hex(random_bytes(32));
        $branch->save();

        return $branch;
    }
}
