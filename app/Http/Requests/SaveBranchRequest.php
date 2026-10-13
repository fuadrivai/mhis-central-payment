<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
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
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('branches', 'code')->ignore($branchId)],
            'va_fixed_prefix' => ['required', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('branches', 'va_fixed_prefix')->ignore($branchId)],
            'va_dynamic_prefix' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/', Rule::unique('branches', 'va_dynamic_prefix')->ignore($branchId)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.alpha_dash' => 'Kode cabang hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'va_fixed_prefix.regex' => 'Prefix Fixed VA wajib tepat 8 digit angka.',
            'va_dynamic_prefix.regex' => 'Prefix Dynamic VA hanya boleh berisi angka.',
        ];
    }
}
