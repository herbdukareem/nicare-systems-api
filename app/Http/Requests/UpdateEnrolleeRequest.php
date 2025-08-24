<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UpdateEnrolleeRequest
 *
 * Handles validation when updating an enrollee.
 */
class UpdateEnrolleeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollee_id' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('enrollees', 'enrollee_id')->ignore($this->route('enrollee')),
            ],
            'nin' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('enrollees', 'nin')->ignore($this->route('enrollee')),
            ],
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'date_of_birth' => 'sometimes|required|date',
            'gender' => 'sometimes|required|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
            'address' => 'nullable|string',
            'enrollee_type_id' => 'sometimes|required|exists:enrollee_types,id',
            'enrollee_category' => 'nullable|string|max:255',
            'facility_id' => 'sometimes|required|exists:facilities,id',
            'lga_id' => 'sometimes|required|exists:lgas,id',
            'ward_id' => 'sometimes|required|exists:wards,id',
            'village' => 'nullable|string|max:255',
            'premium_id' => 'nullable|exists:premiums,id',
            'employment_detail_id' => 'nullable|exists:employment_details,id',
            'funding_type_id' => 'nullable|exists:funding_types,id',
            'benefactor_id' => 'nullable|exists:benefactors,id',
            'capitation_start_date' => 'nullable|date',
            'approval_date' => 'nullable|date',
            'status' => 'nullable|in:pending,approved,rejected,suspended',
            'created_by' => 'sometimes|required|exists:users,id',
            'approved_by' => 'nullable|exists:users,id',
        ];
    }
}
