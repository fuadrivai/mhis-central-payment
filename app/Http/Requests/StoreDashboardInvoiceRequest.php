<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoices.create') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'external_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique('invoices', 'bill_code')
                    ->where('branch_id', $this->input('branch_id'))
                    ->where('source', 'dashboard'),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'description' => ['required', 'string', 'max:255'],
            'expired_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Pilih cabang pemilik invoice.',
            'branch_id.exists' => 'Cabang yang dipilih tidak aktif atau tidak ditemukan.',
            'bank_id.required' => 'Pilih bank penyedia VA.',
            'bank_id.exists' => 'Bank yang dipilih tidak aktif atau tidak ditemukan.',
            'external_id.unique' => 'External ID sudah digunakan untuk cabang ini.',
            'amount.gt' => 'Amount harus lebih besar dari 0.',
            'description.required' => 'Description wajib diisi.',
            'expired_at.date_format' => 'Tanggal kedaluwarsa harus berformat tanggal dan waktu yang valid.',
            'expired_at.after' => 'Tanggal kedaluwarsa harus di masa mendatang.',
        ];
    }
}
