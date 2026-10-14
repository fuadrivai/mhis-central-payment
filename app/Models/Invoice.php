<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id',
    'student_id',
    'payment_type_id',
    'va_type',
    'invoice_number',
    'source',
    'bill_code',
    'bill_name',
    'customer_name',
    'customer_phone',
    'email',
    'amount',
    'fee_amount',
    'total_amount',
    'status',
    'expired_at',
    'paid_at',
])]
class Invoice extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * A pending invoice whose expiry has passed is shown as expired.
     */
    public function displayStatus(): string
    {
        if ($this->status === 'pending' && $this->expired_at?->isPast()) {
            return 'expired';
        }

        return $this->status;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<PaymentType, $this>
     */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * @return HasOne<VirtualAccount, $this>
     */
    public function virtualAccount(): HasOne
    {
        return $this->hasOne(VirtualAccount::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /**
     * @return HasMany<H2hApiLog, $this>
     */
    public function h2hApiLogs(): HasMany
    {
        return $this->hasMany(H2hApiLog::class);
    }
}
