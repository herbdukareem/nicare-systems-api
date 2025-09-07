<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles validation for updating an LGA.
 */
class UpdateLgaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => [
                'sometimes', 'string', 'max:255',
                Rule::unique('lgas', 'name')->ignore($this->lga),
            ],
            'code'           => [
                'sometimes', 'string', 'max:255',
                Rule::unique('lgas', 'code')->ignore($this->lga),
            ],
            'zone'           => 'nullable|integer',
            'baseline'       => 'nullable|integer',
            'total_enrolled' => 'nullable|integer',
            'status'         => 'nullable|in:active,inactive',
        ];
    }
}
