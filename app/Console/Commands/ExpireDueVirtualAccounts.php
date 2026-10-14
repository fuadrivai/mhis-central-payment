<?php

namespace App\Console\Commands;

use App\Jobs\VoidExpiredVirtualAccount;
use App\Models\VirtualAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('virtual-accounts:expire-due')]
#[Description('Queue active virtual accounts whose configured expiry has passed')]
class ExpireDueVirtualAccounts extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        VirtualAccount::query()
            ->select(['id', 'invoice_id'])
            ->where('status', 'active')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('invoice_id')
                    ->orWhereHas('invoice', fn (Builder $invoiceQuery): Builder => $invoiceQuery
                        ->whereIn('status', ['pending', 'expired']));
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $virtualAccounts) use (&$dispatched): void {
                foreach ($virtualAccounts as $virtualAccount) {
                    VoidExpiredVirtualAccount::dispatch($virtualAccount->id);
                    $dispatched++;
                }
            });

        $this->info("Queued {$dispatched} expired virtual account(s) for deactivation.");

        return self::SUCCESS;
    }
}
