<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating a funding type.
 */
class StoreFundingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255|unique:funding_types,name',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:active,inactive',
        ];
    }
}
