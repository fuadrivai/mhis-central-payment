<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'source' => $this->source,
            'bill_code' => $this->bill_code,
            'bill_name' => $this->bill_name,
            'customer_name' => $this->customer_name,
            'email' => $this->email,
            'amount' => $this->amount,
            'fee_amount' => $this->fee_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'expired_at' => $this->expired_at?->toIso8601String(),
            'virtual_account' => $this->whenLoaded(
                'virtualAccount',
                fn (): ?VirtualAccountResource => $this->virtualAccount ? new VirtualAccountResource($this->virtualAccount->loadMissing('bank')) : null,
            ),
        ];
    }
}
