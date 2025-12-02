<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\Bundle;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Referral;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Simplified Admission Service
 *
 * Core Rule: Admission REQUIRES a validated UTN (approved referral with utn_validated = true)
 */
class AdmissionService
{
    /**
     * Create a new admission record
     *
     * ENFORCES: Referral must be approved and UTN validated
     */
    public function createAdmission(array $data): Admission
    {
        // REQUIRED: referral_id must be provided
        if (empty($data['referral_id'])) {
            throw new InvalidArgumentException('Referral ID is required for admission');
        }

        $referral = Referral::findOrFail($data['referral_id']);

        // ENFORCE: Referral must be approved
        if ($referral->status !== 'approved') {
            throw new InvalidArgumentException('Referral must be approved before admission');
        }

        // ENFORCE: UTN must be validated
        if (!$referral->utn_validated) {
            throw new InvalidArgumentException('UTN must be validated by receiving facility before admission');
        }

        $enrollee = Enrollee::findOrFail($data['enrollee_id']);
        $facility = Facility::findOrFail($data['facility_id']);

        // Check for existing active admission
        $activeAdmission = $this->getActiveAdmission($enrollee->id);
        if ($activeAdmission) {
            throw new InvalidArgumentException('Patient already has an active admission');
        }

        // Auto-match bundle from principal diagnosis
        $bundleId = null;
        if (!empty($data['principal_diagnosis_icd10'])) {
            $bundle = Bundle::findByDiagnosis($data['principal_diagnosis_icd10']);
            $bundleId = $bundle?->id;
        }

        return Admission::create([
            'referral_id' => $referral->id,
            'enrollee_id' => $enrollee->id,
            'nicare_number' => $enrollee->enrollee_id,
            'facility_id' => $facility->id,
            'bundle_id' => $bundleId,
            'principal_diagnosis_icd10' => $data['principal_diagnosis_icd10'] ?? null,
            'principal_diagnosis_description' => $data['principal_diagnosis_description'] ?? null,
            'admission_date' => $data['admission_date'] ?? now(),
            'status' => 'active',
            'ward_type' => $data['ward_type'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Discharge a patient
     */
    public function dischargePatient(Admission $admission, array $data): Admission
    {
        $admission->update([
            'discharge_date' => $data['discharge_date'] ?? now(),
            'status' => 'discharged',
            'discharge_summary' => $data['discharge_summary'] ?? null,
            'ward_days' => $admission->getStayDuration(),
            'discharged_by' => auth()->id(),
        ]);

        return $admission->fresh();
    }

    /**
     * Get active admission for an enrollee
     */
    public function getActiveAdmission(int $enrolleeId): ?Admission
    {
        return Admission::where('enrollee_id', $enrolleeId)
            ->active()
            ->first();
    }

    /**
     * Check if patient can be admitted with the given referral
     */
    public function canAdmit(int $referralId): array
    {
        $referral = Referral::find($referralId);

        if (!$referral) {
            return [
                'can_admit' => false,
                'reason' => 'Referral not found',
            ];
        }

        if ($referral->status !== 'approved') {
            return [
                'can_admit' => false,
                'reason' => 'Referral is not approved',
            ];
        }

        if (!$referral->utn_validated) {
            return [
                'can_admit' => false,
                'reason' => 'UTN has not been validated by receiving facility',
            ];
        }

        // Check for existing active admission
        $enrollee = Enrollee::where('enrollee_id', $referral->nicare_number)->first();
        if ($enrollee) {
            $activeAdmission = $this->getActiveAdmission($enrollee->id);
            if ($activeAdmission) {
                return [
                    'can_admit' => false,
                    'reason' => 'Patient already has an active admission',
                    'active_admission' => $activeAdmission,
                ];
            }
        }

        return ['can_admit' => true, 'referral' => $referral];
    }

    /**
     * Get admission history for an enrollee
     */
    public function getAdmissionHistory(int $enrolleeId): Collection
    {
        return Admission::where('enrollee_id', $enrolleeId)
            ->orderBy('admission_date', 'desc')
            ->get();
    }
}

