<?php

namespace App\Http\Requests\Api;

use App\Models\Bank;
use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceApiRequest extends FormRequest
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
        /** @var Branch $branch */
        $branch = $this->attributes->get('branch');

        return [
            'bank_id' => ['required', 'integer', 'exists:banks,id,is_active,1'],
            'student_id' => ['nullable', 'integer', Rule::exists('students', 'id')->where('branch_id', $branch->id)],
            'payment_type' => ['nullable', 'string', 'exists:payment_types,code,is_active,1'],
            'source' => ['nullable', 'string', 'max:100'],
            'invoice_duration' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'bill_code' => [
                'required', 'string', 'max:100',
                Rule::unique('invoices', 'bill_code')
                    ->where('branch_id', $branch->id)
                    ->where('source', $this->input('source', 'api')),
            ],
            'bill_name' => ['required', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'expired_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d H:i:s', 'after:now'],
        ];
    }

    /**
     * The branch always comes from the API key; a single active bank is selected automatically.
     */
    protected function prepareForValidation(): void
    {
        $aliases = [
            'bill_code' => 'external_id',
            'bill_name' => 'description',
            'email' => 'payer_email',
        ];
        $mappedValues = [];

        foreach ($aliases as $field => $alias) {
            if (! $this->exists($field) && $this->exists($alias)) {
                $mappedValues[$field] = $this->input($alias);
            }
        }

        if (! $this->exists('expired_at') && $this->exists('invoice_duration')) {
            $duration = filter_var($this->input('invoice_duration'), FILTER_VALIDATE_INT);

            if ($duration !== false && $duration > 0 && $duration <= 2147483647) {
                $mappedValues['expired_at'] = now()->addSeconds($duration)->format('Y-m-d H:i:s');
            }
        }

        if ($mappedValues !== []) {
            $this->merge($mappedValues);
        }

        if (! $this->filled('bank_id')) {
            $activeBankIds = Bank::query()->active()->limit(2)->pluck('id');

            if ($activeBankIds->count() === 1) {
                $this->merge(['bank_id' => $activeBankIds->first()]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_id.required' => 'Pilih bank penyedia VA.',
            'bill_code.unique' => 'Bill code sudah digunakan untuk source ini pada cabang Anda.',
            'student_id.exists' => 'Siswa tidak ditemukan pada cabang Anda.',
            'expired_at.date_format' => 'Format expired_at harus Y-m-d H:i atau Y-m-d H:i:s, contoh 2027-06-30 23:59.',
            'expired_at.after' => 'Tanggal kedaluwarsa harus di masa mendatang.',
        ];
    }
}
