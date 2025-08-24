<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class StorePremiumRequest
 *
 * Handles validation when creating a premium.
 */
class StorePremiumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => 'required|string|max:16|unique:premiums,pin',
            'pin_raw' => 'required|string|max:16',
            'serial_no' => 'required|string|max:20|unique:premiums,serial_no',
            'pin_type' => 'required|in:individual,family,group',
            'pin_category' => 'required|in:formal,informal,vulnerable,retiree',
            'benefit_type' => 'required|in:basic,standard,premium',
            'amount' => 'required|numeric|min:0',
            'date_generated' => 'required|date',
            'date_used' => 'nullable|date',
            'date_expired' => 'required|date|after:date_generated',
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
