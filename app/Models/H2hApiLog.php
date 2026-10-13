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
    'endpoint',
    'http_method',
    'response_status',
    'request_payload',
    'response_payload',
    'duration_ms',
    'error_message',
])]
class H2hApiLog extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
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
