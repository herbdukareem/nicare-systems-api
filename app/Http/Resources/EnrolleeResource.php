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
            'legacy_id' => $this->legacy_id,
            'legacy_enrollee_id' => $this->legacy_enrollee_id,
            'nin' => $this->nin,
            'nin_verification_status' => $this->ninVerificationStatus(),
            'nin_verification_label' => $this->ninVerificationLabel(),
            'nin_verified_at' => $this->nin_verified_at,
            'nin_verification_provider' => $this->nin_verification_provider,
            'nin_verification' => [
                'status' => $this->ninVerificationStatus(),
                'label' => $this->ninVerificationLabel(),
                'verified_at' => $this->nin_verified_at,
                'provider' => $this->nin_verification_provider,
                'verified_by' => new UserResource($this->whenLoaded('ninVerifiedBy')),
                'data' => $this->nin_verification_data,
                'meta' => $this->nin_verification_meta,
            ],
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'name' => trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name), // Full name for frontend
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->date_of_birth ? now()->diffInYears($this->date_of_birth) : null,
            'sex' => $this->sex,
            'gender' => $this->sex == 1 ? 'Male' : ($this->sex == 2 ? 'Female' : 'Other'),
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'village' => $this->village,
            'pregnant' => $this->pregnant,
            'disability' => $this->disability,
            'occupation' => $this->occupation,
            'image_url' => $this->image_url,
            'provided_image_url' => $this->providedEnrollmentPhotoUrl(),
            'enrollee_type' => new EnrolleeTypeResource($this->whenLoaded('enrolleeType')),
            'type' => $this->whenLoaded('enrolleeType', function() {
                return $this->enrolleeType->name ?? null;
            }),
            'insurance_programme' => $this->whenLoaded('insuranceProgramme'),
            'enrollee_category' => $this->whenLoaded('enrolleeCategory'),
            'premium_plan_id' => $this->premium_plan_id,
            'premium_pin_id' => $this->premium_pin_id,
            'premium_purchase_id' => $this->premium_purchase_id,
            'premium_plan' => $this->whenLoaded('premiumPlan'),
            'premium_pin' => $this->whenLoaded('premiumPin'),
            'premium_purchase' => $this->whenLoaded('premiumPurchase'),
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
            'ward_name' => $this->whenLoaded('ward', function() {
                return $this->ward->name ?? null;
            }),
            'employment_detail' => new EmploymentDetailResource($this->whenLoaded('employmentDetail')),
            'funding_type' => new FundingTypeResource($this->whenLoaded('fundingType')),
            'benefactor' => new BenefactorResource($this->whenLoaded('benefactor')),
            'capitation_start_date' => $this->capitation_start_date,
            'coverage_start_date' => $this->coverage_start_date,
            'coverage_end_date' => $this->coverage_end_date,
            'coverage_label' => $this->coverage_label,
            'coverage_status' => $this->coverageStatus(),
            'is_no_expiry' => $this->hasNoExpiryCoverage(),
            'has_valid_coverage' => $this->hasValidCoverage(),
            'approval_date' => $this->approval_date,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'enrollment_source' => $this->enrollment_source ?? 'staff',
            'enrollment_location' => $this->enrollmentLocation(),
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

    private function coverageStatus(): string
    {
        if (!$this->coverage_start_date) {
            return 'pending';
        }

        if ($this->hasValidCoverage()) {
            return 'active';
        }

        if ($this->coverage_start_date->isFuture()) {
            return 'future';
        }

        return 'expired';
    }

    private function ninVerificationStatus(): string
    {
        if (blank($this->nin)) {
            return \App\Models\Enrollee::NIN_VERIFICATION_NOT_PROVIDED;
        }

        return $this->nin_verification_status ?: \App\Models\Enrollee::NIN_VERIFICATION_NOT_STARTED;
    }

    private function ninVerificationLabel(): string
    {
        return match ($this->ninVerificationStatus()) {
            \App\Models\Enrollee::NIN_VERIFICATION_VERIFIED => 'Verified',
            \App\Models\Enrollee::NIN_VERIFICATION_FAILED => 'Verification Failed',
            \App\Models\Enrollee::NIN_VERIFICATION_NOT_PROVIDED => 'NIN Not Provided',
            default => 'Not Verified',
        };
    }

    private function enrollmentLocation(): ?array
    {
        $location = is_array($this->enrollment_location_audit) ? $this->enrollment_location_audit : null;
        if (!$location) {
            return null;
        }

        $formatPoint = function (?array $point): ?array {
            if (!$point || !isset($point['latitude'], $point['longitude'])) {
                return null;
            }

            return [
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'accuracy_meters' => $point['accuracy_meters'] ?? null,
                'recorded_at' => $point['recorded_at'] ?? null,
                'source' => $point['source'] ?? 'device_gps',
                'mocked' => (bool) ($point['mocked'] ?? false),
                'google_maps_url' => $point['google_maps_url'] ?? sprintf('https://maps.google.com/?q=%s,%s', $point['latitude'], $point['longitude']),
            ];
        };

        return [
            'permission_status' => $location['permission_status'] ?? 'unknown',
            'mocked' => (bool) ($location['mocked'] ?? false),
            'error' => $location['error'] ?? null,
            'captured_via' => $location['captured_via'] ?? 'mobile_officer_device',
            'capture_location' => $formatPoint(is_array($location['capture_location'] ?? null) ? $location['capture_location'] : null),
            'submit_location' => $formatPoint(is_array($location['submit_location'] ?? null) ? $location['submit_location'] : null),
        ];
    }
}
