<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating an audit trail.
 */
class StoreAuditTrailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollee_id' => 'required|exists:enrollees,id',
            'action'      => 'required|string|max:255',
            'description' => 'required|string',
            'user_id'     => 'required|exists:users,id',
            'old_values'  => 'nullable|json',
            'new_values'  => 'nullable|json',
        ];
    }
}
