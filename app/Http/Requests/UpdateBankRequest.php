<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UpdateBankRequest
 *
 * Handles validation for updating a bank.
 */
class UpdateBankRequest extends FormRequest
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
                Rule::unique('banks', 'code')->ignore($this->route('bank')),
            ],
            'sort_code' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
