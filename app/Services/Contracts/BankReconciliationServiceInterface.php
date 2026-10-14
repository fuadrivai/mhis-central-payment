<?php

namespace App\Services\Contracts;

use App\Models\Bank;
use App\Models\BankReconciliation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface BankReconciliationServiceInterface
{
    /**
     * @return Collection<int, Bank>
     */
    public function activeBanks(): Collection;

    /**
     * @return LengthAwarePaginator<int, BankReconciliation>
     */
    public function paginateRuns(int $perPage = 20): LengthAwarePaginator;

    public function reconcile(
        int $bankId,
        int $uploadedBy,
        string $periodFrom,
        string $periodTo,
        UploadedFile $statementFile,
    ): BankReconciliation;

    public function detail(BankReconciliation $reconciliation, int $perPage = 50): BankReconciliation;
}
