<?php

namespace App\Services\Contracts;

use App\Enums\VirtualAccountType;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\VirtualAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface VirtualAccountServiceInterface
{
    public const OUTCOME_ACTIVE = 'active';

    public const OUTCOME_VOIDED = 'voided';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_SERVER_ERROR = 'server_error';

    public const OUTCOME_UNKNOWN = 'unknown';

    public const OUTCOME_NOT_ELIGIBLE = 'not_eligible';

    public const OUTCOME_UPDATED = 'updated';

    public function paginate(?VirtualAccountType $type = null, int $perPage = 15, ?string $search = null): LengthAwarePaginator;

    public function activeBranches(): Collection;

    public function activeBanks(): Collection;

    /**
     * Create a fixed VA for a new student, or for an existing one when `student_id` is given.
     *
     * @param  array<string, mixed>  $data
     * @return array{outcome: self::OUTCOME_*, virtualAccount: VirtualAccount}
     */
    public function createFixed(array $data): array;

    public function findEditableFixedVirtualAccount(VirtualAccount $virtualAccount): VirtualAccount;

    /**
     * Update a Fixed VA at the bank before saving the local student and VA details.
     *
     * @param  array{name: string, grade_class: ?string, parent_email: string, additional_email: ?string, expired_at: string}  $data
     * @return self::OUTCOME_*
     */
    public function updateFixed(VirtualAccount $virtualAccount, array $data): string;

    /**
     * Validate and register fixed VAs from a CSV file.
     *
     * @return array{created: int, results: array<int, array{row: int, nis: string, outcome: string}>}
     */
    public function bulkCreateFixed(int $branchId, int $bankId, UploadedFile $studentFile): array;

    /**
     * Void a fixed VA owned by the given branch through its bank.
     *
     * @return array{outcome: self::OUTCOME_*, virtualAccount: VirtualAccount}
     */
    public function voidFixedVirtualAccount(Branch $branch, string $virtualAccountNo): array;

    /**
     * Create a dynamic VA that bills the given invoice through the given bank.
     *
     * @return array{outcome: self::OUTCOME_*, virtualAccount: VirtualAccount}
     */
    public function createDynamic(Invoice $invoice, int $bankId): array;

    /**
     * Void an invoice's active dynamic VA through its bank.
     *
     * @return self::OUTCOME_*
     */
    public function voidInvoice(Invoice $invoice): string;

    /**
     * Deactivate an active virtual account whose configured expiry has passed.
     *
     * @return self::OUTCOME_*
     */
    public function expireDueVirtualAccount(VirtualAccount $virtualAccount): string;
}
