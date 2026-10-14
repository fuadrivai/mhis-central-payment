<?php

namespace App\Services\Contracts;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BranchServiceInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * @return array{hasInvoices: bool, hasVirtualAccounts: bool, hasFinancialRecords: bool}
     */
    public function financialState(Branch $branch): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Branch;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Branch $branch, array $data): Branch;

    public function rotateApiKey(Branch $branch): Branch;
}
