<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating a benefactor.
 */
class UpdateBenefactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'sometimes|string|max:255',
            'type' => 'nullable|in:individual,principal_enrollee,employer,government,donor,institution,association,ngo,group,philanthropist',
            'registration_number' => 'nullable|string|max:120',
            'contact_person' => 'nullable|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status'  => 'nullable|integer|in:0,1',
        ];
    }
}
