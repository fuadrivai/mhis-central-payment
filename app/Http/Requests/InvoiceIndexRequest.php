<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceIndexRequest extends FormRequest
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
            'search_by' => ['sometimes', 'required', Rule::in(['bill_code', 'invoice_number', 'email'])],
            'search' => ['nullable', 'string', 'max:255'],
            'date_filters' => ['sometimes', 'array'],
            'date_filters.*' => ['string', Rule::in(['paid_at', 'created_at', 'expired_at'])],
            'date_ranges' => ['sometimes', 'array:paid_at,created_at,expired_at'],
            'date_ranges.paid_at' => ['sometimes', 'array:from,to'],
            'date_ranges.paid_at.from' => ['nullable', 'date_format:Y-m-d'],
            'date_ranges.paid_at.to' => ['nullable', 'date_format:Y-m-d'],
            'date_ranges.created_at' => ['sometimes', 'array:from,to'],
            'date_ranges.created_at.from' => ['nullable', 'date_format:Y-m-d'],
            'date_ranges.created_at.to' => ['nullable', 'date_format:Y-m-d'],
            'date_ranges.expired_at' => ['sometimes', 'array:from,to'],
            'date_ranges.expired_at.from' => ['nullable', 'date_format:Y-m-d'],
            'date_ranges.expired_at.to' => ['nullable', 'date_format:Y-m-d'],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::in(['settled', 'paid', 'pending', 'expired'])],
            'created_from' => ['sometimes', 'array'],
            'created_from.*' => ['string', Rule::in(['api', 'dashboard', 'integration', 'on_demand', 'recurring', 'mobile'])],
            'amount_operator' => ['sometimes', 'required', Rule::in(['equals', 'greater', 'less'])],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
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

                foreach (['paid_at', 'created_at', 'expired_at'] as $dateFilter) {
                    $from = $this->input("date_ranges.{$dateFilter}.from");
                    $to = $this->input("date_ranges.{$dateFilter}.to");

                    if ($from !== null && $to !== null && $from > $to) {
                        $validator->errors()->add(
                            "date_ranges.{$dateFilter}.to",
                            'Tanggal akhir harus sama dengan atau setelah tanggal mulai.',
                        );
                    }
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
            'search_by.in' => 'Pilih pencarian berdasarkan Bill Code, Invoice, atau Email.',
            'date_filters.*.in' => 'Jenis tanggal filter tidak valid.',
            'statuses.*.in' => 'Status invoice yang dipilih tidak valid.',
            'created_from.*.in' => 'Sumber invoice yang dipilih tidak valid.',
            'amount_operator.in' => 'Operator nominal yang dipilih tidak valid.',
            'amount.max' => 'Nominal filter melebihi batas yang didukung.',
        ];
    }
}
