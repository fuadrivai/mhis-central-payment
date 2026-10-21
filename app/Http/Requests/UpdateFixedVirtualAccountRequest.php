<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFixedVirtualAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'grade_class' => ['nullable', 'string', 'max:50'],
            'parent_email' => ['required', 'string', 'email', 'max:255'],
            'additional_email' => ['nullable', 'string', 'email', 'max:255'],
            'expired_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_email.required' => 'Email utama wajib diisi.',
            'parent_email.email' => 'Email utama harus berupa alamat email yang valid.',
            'additional_email.email' => 'Email tambahan harus berupa alamat email yang valid.',
            'expired_at.date_format' => 'Tanggal kedaluwarsa tidak valid.',
            'expired_at.after' => 'Tanggal kedaluwarsa harus di masa mendatang.',
        ];
    }
}
