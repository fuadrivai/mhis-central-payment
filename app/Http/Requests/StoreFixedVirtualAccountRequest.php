<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFixedVirtualAccountRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'exists:branches,id,is_active,1'],
            'va_number_mode' => ['required', 'in:automatic,manual'],
            'nis' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
            'manual_va_number' => ['nullable', 'required_if:va_number_mode,manual', 'string', 'regex:/^[0-9]{16}$/'],
            'name' => ['required', 'string', 'max:255'],
            'nisn' => ['nullable', 'string', 'max:50'],
            'grade_class' => ['nullable', 'string', 'max:50'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['required', 'string', 'email', 'max:255'],
            'additional_email' => ['nullable', 'string', 'email', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
            'expired_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $branch = Branch::query()->active()->find($this->integer('branch_id'));

                if (! $branch) {
                    $validator->errors()->add('branch_id', 'Cabang tidak ditemukan atau sedang nonaktif.');

                    return;
                }

                $vaNumber = $this->string('va_number_mode')->toString() === 'automatic'
                    ? $branch->va_fixed_prefix.$this->string('nis')->toString()
                    : $this->string('manual_va_number')->toString();

                if (! str_starts_with($vaNumber, $branch->va_fixed_prefix)) {
                    $validator->errors()->add(
                        'manual_va_number',
                        'Nomor Fixed VA harus diawali prefix cabang '.$branch->va_fixed_prefix.'.',
                    );
                }

                if (mb_strlen($branch->va_fixed_prefix) !== 8) {
                    $validator->errors()->add(
                        'branch_id',
                        'Prefix Fixed VA cabang harus terdiri dari 8 digit. Periksa pengaturan cabang.',
                    );
                }

                if (mb_strlen($vaNumber) !== 16) {
                    $validator->errors()->add(
                        $this->string('va_number_mode')->toString() === 'automatic' ? 'nis' : 'manual_va_number',
                        'Nomor Fixed VA harus tepat 16 digit.',
                    );
                }

                if ($branch->students()->where('nis', $this->string('nis')->toString())->exists()) {
                    $validator->errors()->add('nis', 'NIS tersebut sudah terdaftar pada cabang ini.');
                }

                if ($branch->students()->where('fixed_va_number', $vaNumber)->exists()) {
                    $validator->errors()->add('manual_va_number', 'Nomor Fixed VA tersebut sudah terdaftar.');
                }

                if ($branch->virtualAccounts()->where('virtual_account_no', $vaNumber)->exists()) {
                    $validator->errors()->add('manual_va_number', 'Nomor VA tersebut sudah digunakan.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.exists' => 'Pilih cabang yang aktif.',
            'nis.regex' => 'NIS wajib tepat 8 digit angka.',
            'parent_email.required' => 'Email orang tua/wali wajib diisi.',
            'parent_email.email' => 'Email orang tua/wali harus berupa alamat email yang valid.',
            'additional_email.email' => 'Email tambahan harus berupa alamat email yang valid.',
            'manual_va_number.required_if' => 'Nomor Fixed VA wajib diisi untuk mode manual.',
            'manual_va_number.regex' => 'Nomor Fixed VA wajib tepat 16 digit angka.',
            'expired_at.date_format' => 'Tanggal kedaluwarsa tidak valid.',
            'expired_at.after' => 'Tanggal kedaluwarsa harus di masa mendatang.',
        ];
    }
}
