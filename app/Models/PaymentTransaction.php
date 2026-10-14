<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'bank_id',
    'invoice_id',
    'virtual_account_id',
    'paid_amount',
    'payment_reference_no',
    'payment_request_id',
    'channel',
    'paid_at',
    'raw_payload',
])]
class PaymentTransaction extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<VirtualAccount, $this>
     */
    public function virtualAccount(): BelongsTo
    {
        return $this->belongsTo(VirtualAccount::class);
    }

    /**
     * @return HasOne<BranchWebhookDelivery, $this>
     */
    public function webhookDelivery(): HasOne
    {
        return $this->hasOne(BranchWebhookDelivery::class);
    }
}
