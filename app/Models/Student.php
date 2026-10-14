<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'nis',
    'nisn',
    'name',
    'grade_class',
    'parent_name',
    'parent_email',
    'additional_email',
    'parent_phone',
    'status',
])]
class Student extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
}
