<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'student_id',
    'invoice_id',
    'va_type',
    'partner_service_id',
    'customer_no',
    'virtual_account_no',
    'virtual_account_name',
    'current_amount',
    'expired_at',
    'status',
    'h2h_response_raw',
])]
class VirtualAccount extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_amount' => 'decimal:2',
            'expired_at' => 'datetime',
            'h2h_response_raw' => 'array',
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
