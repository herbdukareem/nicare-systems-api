<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating an account detail.
 */
class StoreAccountDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name'    => 'required|string|max:255',
            'account_number'  => 'required|string|max:255',
            'bank_id'         => 'required|exists:banks,id',
            'account_type'    => 'nullable|string',
            'accountable_id'  => 'required|integer',
            'accountable_type'=> 'required|string',
            'status'          => 'nullable|in:active,inactive',
        ];
    }
}
