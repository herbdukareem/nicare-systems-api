<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Class UpdateFacilityRequest
 *
 * Handles validation for updating a facility.
 */
class UpdateFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hcp_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('facilities', 'hcp_code')->ignore($this->route('facility')),
            ],
            'name' => 'sometimes|required|string|max:255',
            'ownership' => 'sometimes|required_without:category|in:Public,Private,Faith-Based',
            'category' => 'nullable|in:Public,Private,Faith-Based',
            'type' => 'sometimes|required|in:Primary,Secondary,Tertiary',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'lga_id' => 'sometimes|required|exists:lgas,id',
            'ward_id' => 'sometimes|required|exists:wards,id',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|integer|in:0,1',
            'accreditation_status' => 'nullable|in:active,suspended,revoked',
            'account_detail_id' => 'nullable|exists:account_details,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category') && !$this->has('ownership')) {
            $this->merge(['ownership' => $this->input('category')]);
        }
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
