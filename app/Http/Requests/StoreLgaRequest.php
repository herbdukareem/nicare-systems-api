<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating an LGA.
 */
class StoreLgaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255|unique:lgas,name',
            'code'           => 'required|string|max:255|unique:lgas,code',
            'zone'           => 'nullable|integer',
            'baseline'       => 'nullable|integer',
            'total_enrolled' => 'nullable|integer',
            'status'         => 'nullable|in:active,inactive',
        ];
    }
}
