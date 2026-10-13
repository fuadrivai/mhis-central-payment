<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'invoice_id',
    'virtual_account_id',
    'paid_amount',
    'payment_reference_no',
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
}
