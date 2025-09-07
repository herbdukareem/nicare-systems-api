<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for updating a benefactor.
 */
class UpdateBenefactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'sometimes|string|max:255',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'status'  => 'nullable|in:active,inactive',
        ];
    }
}
