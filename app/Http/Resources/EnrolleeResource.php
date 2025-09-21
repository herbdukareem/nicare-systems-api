<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class EnrolleeResource
 *
 * Transforms Enrollee model for JSON responses.
 */
class EnrolleeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'enrollee_id' => $this->enrollee_id,
            'nin' => $this->nin,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'name' => trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name), // Full name for frontend
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->date_of_birth ? now()->diffInYears($this->date_of_birth) : null,
            'gender' => $this->sex == 1 ? 'Male' : ($this->sex == 2 ? 'Female' : 'Other'),
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'enrollee_type' => new EnrolleeTypeResource($this->whenLoaded('enrolleeType')),
            'type' => $this->whenLoaded('enrolleeType', function() {
                return $this->enrolleeType->name ?? null;
            }),
            'enrollee_category' => $this->enrollee_category,
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'facility_name' => $this->whenLoaded('facility', function() {
                return $this->facility->name ?? null;
            }),
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'lga_name' => $this->whenLoaded('lga', function() {
                return $this->lga->name ?? null;
            }),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'village' => $this->village,
            'premium' => new PremiumResource($this->whenLoaded('premium')),
            'employment_detail' => new EmploymentDetailResource($this->whenLoaded('employmentDetail')),
            'funding_type' => new FundingTypeResource($this->whenLoaded('fundingType')),
            'benefactor' => new BenefactorResource($this->whenLoaded('benefactor')),
            'capitation_start_date' => $this->capitation_start_date,
            'approval_date' => $this->approval_date,
            'status' => $this->status->label,
            'status_label' => null,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'account_detail' => new EnrolleeAccountDetailResource($this->whenLoaded('accountDetail')),
            'enrollment_date' => $this->enrollment_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel()
    {
        switch ($this->status) {
            case 0:
                return 'Pending';
            case 1:
                return 'Active';
            case 2:
                return 'Expired';
            case 3:
                return 'Suspended';
            default:
                return 'Inactive';
        }
    }
}
