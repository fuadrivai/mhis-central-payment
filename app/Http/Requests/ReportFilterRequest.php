<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'report' => $this->input('report', 'summary'),
            'from' => $this->input('from', now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'report' => ['required', Rule::in([
                'summary',
                'students',
                'unpaid',
                'reconciliations',
                'branches',
                'issues',
            ])],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'bank_id' => ['nullable', 'integer', Rule::exists('banks', 'id')],
            'va_type' => ['nullable', Rule::in(['fixed', 'dynamic'])],
            'status' => ['nullable', 'string', Rule::in(array_merge([
                'paid',
                'pending',
                'expired',
                'matched',
                'amount_mismatch',
                'not_found',
                'duplicate',
                'missing_from_bank',
                'failed',
                'verification_required',
            ], array_map('strval', range(400, 599))))],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->date('to')->isAfter(today())) {
                    $validator->errors()->add('to', 'Tanggal akhir tidak boleh melebihi hari ini.');
                }
            },
        ];
    }
}
