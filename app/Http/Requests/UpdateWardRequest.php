<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating a ward.
 */
class UpdateWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'sometimes|string|max:255',
            'lga_id'          => 'sometimes|exists:lgas,id',
            'enrollment_cap'  => 'nullable|integer',
            'total_enrolled'  => 'nullable|integer',
            'settlement_type' => 'nullable|integer|in:0,1,2',
            'status'          => 'nullable|integer|in:0,1',
        ];
    }
}
