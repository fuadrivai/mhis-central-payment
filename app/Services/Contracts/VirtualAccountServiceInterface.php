<?php

namespace App\Services\Contracts;

use App\Enums\VirtualAccountType;
use App\Models\Invoice;
use App\Models\VirtualAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface VirtualAccountServiceInterface
{
    public const OUTCOME_ACTIVE = 'active';

    public const OUTCOME_VOIDED = 'voided';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_SERVER_ERROR = 'server_error';

    public const OUTCOME_UNKNOWN = 'unknown';

    public const OUTCOME_NOT_ELIGIBLE = 'not_eligible';

    public function paginate(?VirtualAccountType $type = null, int $perPage = 15): LengthAwarePaginator;

    public function activeBranches(): Collection;

    public function activeBanks(): Collection;

    /**
     * Create a fixed VA for a new student, or for an existing one when `student_id` is given.
     *
     * @param  array<string, mixed>  $data
     * @return array{outcome: self::OUTCOME_*, virtualAccount: VirtualAccount}
     */
    public function createFixed(array $data): array;

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
