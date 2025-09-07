<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating a village.
 */
class UpdateVillageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'sometimes|string|max:255',
            'ward_id' => 'sometimes|exists:wards,id',
            'status'  => 'nullable|in:active,inactive',
        ];
    }
}
