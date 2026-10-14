<?php

namespace App\Services\Contracts;

use App\Models\VirtualAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FixedVirtualAccountServiceInterface
{
    public const OUTCOME_ACTIVE = 'active';

    public const OUTCOME_REJECTED = 'rejected';

    public const OUTCOME_SERVER_ERROR = 'server_error';

    public const OUTCOME_UNKNOWN = 'unknown';

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function activeBranches(): Collection;

    /**
     * @param  array<string, mixed>  $data
     * @return array{outcome: self::OUTCOME_*, virtualAccount: VirtualAccount}
     */
    public function create(array $data): array;
}
