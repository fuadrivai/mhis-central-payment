<?php

namespace App\Jobs;

use App\Models\VirtualAccount;
use App\Services\Contracts\VirtualAccountServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class VoidExpiredVirtualAccount implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900, 1800];

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $virtualAccountId) {}

    public function uniqueId(): string
    {
        return (string) $this->virtualAccountId;
    }

    public function handle(VirtualAccountServiceInterface $virtualAccounts): void
    {
        $virtualAccount = VirtualAccount::query()->findOrFail($this->virtualAccountId);
        $outcome = $virtualAccounts->expireDueVirtualAccount($virtualAccount);

        if (in_array($outcome, [
            VirtualAccountServiceInterface::OUTCOME_SERVER_ERROR,
            VirtualAccountServiceInterface::OUTCOME_UNKNOWN,
        ], true)) {
            throw new RuntimeException("Virtual account expiry returned [{$outcome}].");
        }
    }
}
