<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating an employment detail.
 */
class StoreEmploymentDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollee_id'          => 'required|exists:enrollees,id',
            'employer_name'        => 'nullable|string|max:255',
            'employer_address'     => 'nullable|string',
            'employer_phone'       => 'nullable|string|max:255',
            'job_title'            => 'nullable|string|max:255',
            'employment_type'      => 'nullable|string|max:255',
            'employment_status'    => 'nullable|string|max:255',
            'monthly_income'       => 'nullable|numeric',
            'employment_start_date'=> 'nullable|date',
            'employment_end_date'  => 'nullable|date',
            'industry'             => 'nullable|string|max:255',
            'job_description'      => 'nullable|string',
            'is_verified'          => 'nullable|boolean',
            'verified_at'          => 'nullable|date',
            'verification_method'  => 'nullable|string|max:255',
            'metadata'             => 'nullable|json',
        ];
    }
}
