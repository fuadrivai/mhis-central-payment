<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MandiriPaymentCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->input('data');

        if (! is_string($data)) {
            return;
        }

        $decodedData = json_decode($data, true);

        if (is_array($decodedData)) {
            $this->merge(['data' => $decodedData]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(['va.paid'])],
            'data' => ['required', 'array:virtualAccountNo,customerNo,virtualAccountName,billCode,billName,partnerServiceId,totalAmount,paidAmount,paymentRequestId,referenceNo,status,channelCode,hashedSourceAccountNo,trxDateTime'],
            'data.virtualAccountNo' => ['required', 'string', 'max:50'],
            'data.customerNo' => ['required', 'string', 'max:50'],
            'data.virtualAccountName' => ['required', 'string', 'max:255'],
            'data.billCode' => ['required', 'string', 'max:100'],
            'data.billName' => ['nullable', 'string', 'max:255'],
            'data.partnerServiceId' => ['nullable', 'string', 'max:50'],
            'data.totalAmount' => ['required', 'string', 'regex:/\A\d{1,12}\.\d{2}\z/'],
            'data.paidAmount' => ['required', 'string', 'regex:/\A\d{1,12}\.\d{2}\z/'],
            'data.paymentRequestId' => ['required', 'string', 'max:100'],
            'data.referenceNo' => ['required', 'string', 'max:100'],
            'data.status' => ['required', 'string', Rule::in(['PAID', 'INACTIVE'])],
            'data.channelCode' => ['nullable', 'string', 'max:50'],
            'data.hashedSourceAccountNo' => ['nullable', 'string', 'max:255'],
            'data.trxDateTime' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:s\Z'],
        ];
    }
}
