<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreBankRequest
 *
 * Handles validation for creating a bank.
 */
class StoreBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:banks,code',
            'sort_code' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
