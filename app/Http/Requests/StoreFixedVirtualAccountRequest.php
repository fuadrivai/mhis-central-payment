<?php

namespace App\Http\Requests;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Student;
use App\Models\VirtualAccount;
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
            'bank_id' => ['required', 'integer', 'exists:banks,id,is_active,1'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'va_number_mode' => ['required', 'in:automatic,manual'],
            'nis' => ['nullable', 'required_without:student_id', 'string', 'regex:/^[0-9]{8}$/'],
            'manual_va_number' => ['nullable', 'required_if:va_number_mode,manual', 'string', 'regex:/^[0-9]{16}$/'],
            'name' => ['nullable', 'required_without:student_id', 'string', 'max:255'],
            'nisn' => ['nullable', 'string', 'max:50'],
            'grade_class' => ['nullable', 'string', 'max:50'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_email' => ['nullable', 'required_without:student_id', 'string', 'email', 'max:255'],
            'additional_email' => ['nullable', 'string', 'email', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
            'expired_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('bank_id')) {
            $activeBankIds = Bank::query()->active()->limit(2)->pluck('id');

            if ($activeBankIds->count() === 1) {
                $this->merge(['bank_id' => $activeBankIds->first()]);
            }
        }
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

                $student = $this->filled('student_id') ? Student::query()->find($this->integer('student_id')) : null;

                if ($student && $student->branch_id !== $branch->id) {
                    $validator->errors()->add('student_id', 'Siswa tidak terdaftar pada cabang yang dipilih.');

                    return;
                }

                $nis = $student?->nis ?? $this->string('nis')->toString();
                $vaNumber = $this->string('va_number_mode')->toString() === 'automatic'
                    ? $branch->va_fixed_prefix.$nis
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

                if (! $student && $branch->students()->where('nis', $nis)->exists()) {
                    $validator->errors()->add('nis', 'NIS tersebut sudah terdaftar pada cabang ini.');
                }

                if (VirtualAccount::query()
                    ->where('bank_id', $this->integer('bank_id'))
                    ->where('virtual_account_no', $vaNumber)
                    ->exists()) {
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
            'bank_id.required' => 'Pilih bank penyedia VA.',
            'bank_id.exists' => 'Pilih bank yang aktif.',
            'nis.regex' => 'NIS wajib tepat 8 digit angka.',
            'parent_email.required_without' => 'Email orang tua/wali wajib diisi.',
            'parent_email.email' => 'Email orang tua/wali harus berupa alamat email yang valid.',
            'additional_email.email' => 'Email tambahan harus berupa alamat email yang valid.',
            'manual_va_number.required_if' => 'Nomor Fixed VA wajib diisi untuk mode manual.',
            'manual_va_number.regex' => 'Nomor Fixed VA wajib tepat 16 digit angka.',
            'expired_at.date_format' => 'Tanggal kedaluwarsa tidak valid.',
            'expired_at.after' => 'Tanggal kedaluwarsa harus di masa mendatang.',
        ];
    }
}
