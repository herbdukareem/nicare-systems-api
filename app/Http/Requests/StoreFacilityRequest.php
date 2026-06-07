<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Class StoreFacilityRequest
 *
 * Handles validation for creating a facility.
 */
class StoreFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hcp_code' => 'required|string|max:255|unique:facilities,hcp_code',
            'name' => 'required|string|max:255',
            'ownership' => 'required_without:category|in:Public,Private,Faith-Based',
            'category' => 'nullable|in:Public,Private,Faith-Based',
            'type' => 'required|in:Primary,Secondary,Tertiary',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'lga_id' => 'required|exists:lgas,id',
            'ward_id' => 'required|exists:wards,id',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
            'accreditation_status' => 'nullable|in:active,suspended,revoked',
            'account_detail_id' => 'nullable|exists:account_details,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ownership' => $this->input('ownership', $this->input('category')),
            'accreditation_status' => $this->input('accreditation_status', 'active'),
            'status' => $this->input('status', 1),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->filled('lga_id') || !$this->filled('ward_id')) {
                return;
            }

            $wardBelongsToLga = \App\Models\Ward::whereKey($this->input('ward_id'))
                ->where('lga_id', $this->input('lga_id'))
                ->exists();

            if (!$wardBelongsToLga) {
                $validator->errors()->add('ward_id', 'The selected ward does not belong to the selected LGA.');
            }
        });
    }
}
