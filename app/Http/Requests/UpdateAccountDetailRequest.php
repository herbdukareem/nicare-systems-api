<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating an account detail.
 */
class UpdateAccountDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_name'     => 'sometimes|string|max:255',
            'account_number'   => 'sometimes|string|max:255',
            'bank_id'          => 'sometimes|exists:banks,id',
            'account_type'     => 'nullable|string',
            'accountable_id'   => 'sometimes|integer',
            'accountable_type' => 'sometimes|string',
            'status'           => 'nullable|in:active,inactive',
        ];
    }
}
