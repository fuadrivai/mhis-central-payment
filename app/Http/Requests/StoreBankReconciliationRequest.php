<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
            'statement_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_id.exists' => 'Pilih bank aktif yang valid.',
            'period_to.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
            'statement_file.mimes' => 'File laporan harus berformat CSV.',
            'statement_file.max' => 'Ukuran file laporan maksimal 10 MB.',
        ];
    }
}
