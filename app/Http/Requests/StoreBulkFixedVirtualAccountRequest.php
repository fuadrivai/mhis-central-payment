<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkFixedVirtualAccountRequest extends FormRequest
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
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'student_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Pilih cabang.',
            'branch_id.exists' => 'Pilih cabang yang aktif.',
            'bank_id.required' => 'Pilih bank penyedia VA.',
            'bank_id.exists' => 'Pilih bank yang aktif.',
            'student_file.required' => 'Pilih file CSV data siswa.',
            'student_file.mimes' => 'File data siswa harus berformat CSV.',
            'student_file.max' => 'Ukuran file CSV maksimal 5 MB.',
        ];
    }
}
