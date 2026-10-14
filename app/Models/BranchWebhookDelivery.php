<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'payment_transaction_id',
    'webhook_url',
    'status',
    'attempts',
    'payload',
    'response_status',
    'response_body',
    'last_error',
    'last_attempt_at',
    'delivered_at',
])]
class BranchWebhookDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'payload' => 'array',
            'response_status' => 'integer',
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<PaymentTransaction, $this>
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
