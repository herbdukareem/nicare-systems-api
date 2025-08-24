<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class EmploymentDetailResource
 *
 * Transforms EmploymentDetail model for JSON responses.
 */
class EmploymentDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'employer_name' => $this->employer_name,
            'employer_address' => $this->employer_address,
            'employer_phone' => $this->employer_phone,
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'monthly_income' => $this->monthly_income,
            'employment_start_date' => $this->employment_start_date,
            'employment_end_date' => $this->employment_end_date,
            'industry' => $this->industry,
            'job_description' => $this->job_description,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at,
            'verification_method' => $this->verification_method,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
