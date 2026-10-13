<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'va_fixed_prefix',
    'va_dynamic_prefix',
    'api_key',
    'is_active',
])]
#[Hidden(['api_key'])]
class Branch extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<VirtualAccount, $this>
     */
    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(VirtualAccount::class);
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

    public function generateDynamicApiKey(): void
    {
        if ($this->api_key === null) {
            $this->api_key = bin2hex(random_bytes(32));
        }
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
