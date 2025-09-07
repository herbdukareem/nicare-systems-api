<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating an audit trail.
 */
class UpdateAuditTrailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollee_id' => 'sometimes|exists:enrollees,id',
            'action'      => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'user_id'     => 'sometimes|exists:users,id',
            'old_values'  => 'nullable|json',
            'new_values'  => 'nullable|json',
        ];
    }
}
