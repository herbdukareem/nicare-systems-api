<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles validation for updating a funding type.
 */
class UpdateFundingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => [
                'sometimes', 'string', 'max:255',
                Rule::unique('funding_types', 'name')->ignore($this->funding_type),
            ],
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,inactive',
        ];
    }
}
