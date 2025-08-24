<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'category' => 'required|in:Public,Private',
            'type' => 'required|in:Primary,Secondary,Tertiary',
            'level' => 'required|in:Basic,Standard,Premium',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'lga_id' => 'required|exists:lgas,id',
            'ward_id' => 'required|exists:wards,id',
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
            'account_detail_id' => 'nullable|exists:account_details,id',
        ];
    }
}
