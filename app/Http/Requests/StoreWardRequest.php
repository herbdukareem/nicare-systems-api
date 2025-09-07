<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating a ward.
 */
class StoreWardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'lga_id'          => 'required|exists:lgas,id',
            'enrollment_cap'  => 'nullable|integer',
            'total_enrolled'  => 'nullable|integer',
            'settlement_type' => 'nullable|in:Urban,Rural',
            'status'          => 'nullable|in:active,inactive',
        ];
    }
}
