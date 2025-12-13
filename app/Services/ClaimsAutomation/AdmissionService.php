<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\ServiceBundle;
use App\Models\Referral;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * AdmissionService
 * 
 * Handles admission creation, discharge, and eligibility checks.
 * Core Rule: Admission REQUIRES approved referral with validated UTN
 */
class AdmissionService
{
    /**
     * Create an admission from an approved referral
     * 
     * @param int $referralId
     * @param array $data - admission_date, principal_diagnosis_icd10, ward_type, etc.
     * @return Admission
     * @throws InvalidArgumentException
     */
    public function createAdmission(int $referralId, array $data): Admission
    {
        // Validate referral exists and is eligible
        $referral = Referral::find($referralId);
        if (!$referral) {
            throw new ModelNotFoundException("Referral not found");
        }

       

        // Check referral is approved
        if (strtolower($referral->status) !== 'approved') {
            throw new InvalidArgumentException(
                "Cannot create admission: Referral must be approved. Current status: {$referral->status}"
            );
        }

        // Check UTN is validated
        if (!$referral->utn_validated) {
            throw new InvalidArgumentException(
                "Cannot create admission: UTN must be validated before admission"
            );
        }

        // Check no active admission exists for this enrollee
        $activeAdmission = Admission::where('enrollee_id', $referral->enrollee_id)
            ->where('status', 'active')
            ->first();

        if ($activeAdmission) {
            throw new InvalidArgumentException(
                "Cannot create admission: Enrollee already has an active admission (ID: {$activeAdmission->id})"
            );
        }

     

        // Auto-match bundle from principal diagnosis ICD-10 code
        $icd10Code = $data['principal_diagnosis_icd10'] ?? null;
        $bundle = null;

       

        // Create admission
        $admission = new Admission([
            'referral_id' => $referralId,
            'enrollee_id' => $referral->enrollee_id,
            'nicare_number' => $referral->enrollee->enrollee_id,
            'facility_id' => $referral->receiving_facility_id,
            'service_bundle_id' => $referral->service_bundle_id,
            'principal_diagnosis_icd10' => $icd10Code,
            'principal_diagnosis_description' => $data['principal_diagnosis_description'] ?? null,
            'admission_date' => $data['admission_date'] ?? now(),
            'ward_type' => $data['ward_type'] ?? null,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        $admission->save();

        return $admission;
    }

    /**
     * Discharge a patient
     * 
     * @param Admission $admission
     * @param array $data - discharge_date, discharge_summary, etc.
     * @return Admission
     */
    public function dischargePatient(Admission $admission, array $data): Admission
    {
        if ($admission->status === 'discharged') {
            throw new InvalidArgumentException("Admission is already discharged");
        }

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => $data['discharge_date'] ?? now(),
            'discharge_summary' => $data['discharge_summary'] ?? null,
            'ward_days' => $data['ward_days'] ?? null,
            'discharged_by' => auth()->id(),
        ]);

        return $admission;
    }

    /**
     * Check if an admission can be created for a referral
     * 
     * @param int $referralId
     * @return array - ['can_admit' => bool, 'reason' => string]
     */
    public function canAdmit(int $referralId): array
    {
        $referral = Referral::find($referralId);
        if (!$referral) {
            return ['can_admit' => false, 'reason' => 'Referral not found'];
        }

        if ($referral->status !== 'approved') {
            return ['can_admit' => false, 'reason' => "Referral not approved. Status: {$referral->status}"];
        }

        if (!$referral->utn_validated) {
            return ['can_admit' => false, 'reason' => 'UTN not validated'];
        }

        $activeAdmission = Admission::where('enrollee_id', $referral->enrollee_id)
            ->where('status', 'active')
            ->first();

        if ($activeAdmission) {
            return ['can_admit' => false, 'reason' => "Enrollee has active admission: {$activeAdmission->admission_code}"];
        }

        return ['can_admit' => true, 'reason' => 'Eligible for admission'];
    }

    /**
     * Get active admission for an enrollee
     * 
     * @param int $enrolleeId
     * @return Admission|null
     */
    public function getActiveAdmission(int $enrolleeId): ?Admission
    {
        return Admission::where('enrollee_id', $enrolleeId)
            ->where('status', 'active')
            ->first();
    }
}
