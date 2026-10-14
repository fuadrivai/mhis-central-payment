<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreFixedVirtualAccountRequest;
use App\Models\Branch;

class StoreFixedVirtualAccountApiRequest extends StoreFixedVirtualAccountRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expired_at' => ['required', 'date_format:Y-m-d H:i', 'after:now'],
        ];
    }

    /**
     * The branch always comes from the API key, never from the request body.
     */
    protected function prepareForValidation(): void
    {
        /** @var Branch $branch */
        $branch = $this->attributes->get('branch');

        $this->merge(['branch_id' => $branch->id]);

        parent::prepareForValidation();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'expired_at.date_format' => 'Format expired_at harus Y-m-d H:i, contoh 2027-06-30 23:59.',
        ];
    }
}
