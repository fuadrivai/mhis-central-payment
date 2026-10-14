<?php

namespace App\Http\Resources;

use App\Models\VirtualAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VirtualAccount
 */
class VirtualAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'virtual_account_no' => $this->virtual_account_no,
            'virtual_account_name' => $this->virtual_account_name,
            'va_type' => $this->va_type,
            'status' => $this->status,
            'current_amount' => $this->current_amount,
            'expired_at' => $this->expired_at?->toIso8601String(),
            'bank' => $this->whenLoaded('bank', fn (): array => [
                'code' => $this->bank->code,
                'name' => $this->bank->name,
            ]),
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student->id,
                'nis' => $this->student->nis,
                'name' => $this->student->name,
            ]),
        ];
    }
}
