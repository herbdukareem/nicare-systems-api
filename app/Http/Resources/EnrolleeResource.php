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
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->date_of_birth ? now()->diffInYears($this->date_of_birth) : null,
            'gender' => $this->sex == 1 ? 'Male' : ($this->sex == 2 ? 'Female' : 'Other'),
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'village' => $this->village,
            'pregnant' => $this->pregnant,
            'disability' => $this->disability,
            'occupation' => $this->occupation,
            'image_url' => $this->image_url,
            'enrollee_type' => new EnrolleeTypeResource($this->whenLoaded('enrolleeType')),
            'type' => $this->whenLoaded('enrolleeType', function() {
                return $this->enrolleeType->name ?? null;
            }),
            'insurance_programme' => $this->whenLoaded('insuranceProgramme'),
            'enrollee_category' => $this->whenLoaded('enrolleeCategory'),
            'premium_plan_id' => $this->premium_plan_id,
            'premium_pin_id' => $this->premium_pin_id,
            'premium_plan' => $this->whenLoaded('premiumPlan'),
            'premium_pin' => $this->whenLoaded('premiumPin'),
            'benefit_package' => $this->whenLoaded('benefitPackage'),
            'vulnerable_group' => $this->whenLoaded('vulnerableGroup'),
            'enrollment_phase' => $this->whenLoaded('enrollmentPhase'),
            'principal' => $this->whenLoaded('principal'),
            'dependants' => $this->whenLoaded('dependants'),
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'facility_name' => $this->whenLoaded('facility', function() {
                return $this->facility->name ?? null;
            }),
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'lga_name' => $this->whenLoaded('lga', function() {
                return $this->lga->name ?? null;
            }),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'employment_detail' => new EmploymentDetailResource($this->whenLoaded('employmentDetail')),
            'funding_type' => new FundingTypeResource($this->whenLoaded('fundingType')),
            'benefactor' => new BenefactorResource($this->whenLoaded('benefactor')),
            'capitation_start_date' => $this->capitation_start_date,
            'coverage_start_date' => $this->coverage_start_date,
            'coverage_end_date' => $this->coverage_end_date,
            'coverage_label' => $this->coverage_label,
            'is_no_expiry' => $this->hasNoExpiryCoverage(),
            'has_valid_coverage' => $this->hasValidCoverage(),
            'approval_date' => $this->approval_date,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'creator' => new UserResource($this->whenLoaded('createdBy')),
            'approver' => new UserResource($this->whenLoaded('approvedBy')),
            'duplicate_reviewed_by' => new UserResource($this->whenLoaded('duplicateReviewedBy')),
            'account_detail' => new EnrolleeAccountDetailResource($this->whenLoaded('accountDetail')),
            'enrollment_date' => $this->enrollment_date,
            'relationship_to_principal' => $this->relationship_to_principal,
            'is_possible_duplicate' => (bool) $this->is_possible_duplicate,
            'duplicate_reviewed' => (bool) $this->duplicate_reviewed,
            'duplicate_flags' => $this->whenLoaded('duplicateFlags'),
            'facility_transfers' => $this->whenLoaded('facilityTransfers'),
            'dependants_count' => $this->dependants_count ?? $this->whenCounted('dependants'),
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
                return 'Rejected';
            case 3:
                return 'Suspended';
            case 4:
                return 'Expired / Inactive';
            default:
                return 'Pending';
        }
    }
}
