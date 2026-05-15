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
            'status'         => 'nullable|integer|in:0,1',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('code') && $this->filled('name')) {
            $this->merge(['code' => str($this->input('name'))->slug('_')->upper()->toString()]);
        }
    }
}
