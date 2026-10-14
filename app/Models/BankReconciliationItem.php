<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_reconciliation_id',
    'payment_transaction_id',
    'row_number',
    'reference_no',
    'statement_amount',
    'internal_amount',
    'statement_paid_at',
    'status',
    'raw_data',
])]
class BankReconciliationItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'statement_amount' => 'decimal:2',
            'internal_amount' => 'decimal:2',
            'statement_paid_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<BankReconciliation, $this>
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    /**
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
