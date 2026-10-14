<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bank_id',
    'uploaded_by',
    'original_filename',
    'period_from',
    'period_to',
    'matched_count',
    'amount_mismatch_count',
    'not_found_count',
    'duplicate_count',
    'missing_from_bank_count',
])]
class BankReconciliation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'matched_count' => 'integer',
            'amount_mismatch_count' => 'integer',
            'not_found_count' => 'integer',
            'duplicate_count' => 'integer',
            'missing_from_bank_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<BankReconciliationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BankReconciliationItem::class);
    }
}
