<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UpdateEnrolleeTypeRequest
 *
 * Handles validation when updating an existing EnrolleeType.
 */
class UpdateEnrolleeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('enrollee_types', 'code')->ignore($this->route('enrollee_type')),
            ],
            'description' => 'nullable|string',
            'premium_duration_months' => 'nullable|integer|min:1',
            'premium_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
