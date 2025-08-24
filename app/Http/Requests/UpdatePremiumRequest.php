<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Class UpdatePremiumRequest
 *
 * Handles validation when updating a premium.
 */
class UpdatePremiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => [
                'sometimes',
                'required',
                'string',
                'max:16',
                Rule::unique('premiums', 'pin')->ignore($this->route('premium')),
            ],
            'pin_raw' => 'sometimes|required|string|max:16',
            'serial_no' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('premiums', 'serial_no')->ignore($this->route('premium')),
            ],
            'pin_type' => 'sometimes|required|in:individual,family,group',
            'pin_category' => 'sometimes|required|in:formal,informal,vulnerable,retiree',
            'benefit_type' => 'sometimes|required|in:basic,standard,premium',
            'amount' => 'sometimes|required|numeric|min:0',
            'date_generated' => 'sometimes|required|date',
            'date_used' => 'nullable|date',
            'date_expired' => 'sometimes|required|date|after:date_generated',
            'status' => 'nullable|in:available,used,expired,suspended',
            'used_by' => 'nullable|exists:users,id',
            'agent_reg_number' => 'nullable|string|max:255',
            'lga_id' => 'nullable|exists:lgas,id',
            'ward_id' => 'nullable|exists:wards,id',
            'payment_id' => 'nullable|string|max:255',
            'request_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ];
    }
}
