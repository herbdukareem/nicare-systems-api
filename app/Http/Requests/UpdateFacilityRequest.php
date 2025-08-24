<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'category' => 'sometimes|required|in:Public,Private',
            'type' => 'sometimes|required|in:Primary,Secondary,Tertiary',
            'level' => 'sometimes|required|in:Basic,Standard,Premium',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'lga_id' => 'sometimes|required|exists:lgas,id',
            'ward_id' => 'sometimes|required|exists:wards,id',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
            'account_detail_id' => 'nullable|exists:account_details,id',
        ];
    }
}
