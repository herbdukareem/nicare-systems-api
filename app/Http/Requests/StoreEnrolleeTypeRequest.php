<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StoreEnrolleeTypeRequest
 *
 * Handles validation when creating a new EnrolleeType.
 */
class StoreEnrolleeTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Implement authorization logic (e.g., check user role) as needed.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:enrollee_types,code',
            'description' => 'nullable|string',
            'premium_duration_months' => 'nullable|integer|min:1',
            'premium_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
        ];
    }
}
