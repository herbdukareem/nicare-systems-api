<?php

namespace App\Services;

use App\Models\Referral;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Handles Referral creation and retrieval logic.
 */
class ReferralService
{
    /**
     * Create a referral with optional requested services.
     */
    public function create(array $data): Referral
    {
        $payload = [
            'enrollee_id' => $data['enrollee_id'],
            'referring_facility_id' => $data['referring_facility_id'],
            'receiving_facility_id' => $data['receiving_facility_id'],
            'presenting_complains' => $data['presenting_complains'] ?? null,
            'reasons_for_referral' => $data['reasons_for_referral'] ?? null,
            'treatments_given' => $data['treatments_given'] ?? null,
            'investigations_done' => $data['investigations_done'] ?? null,
            'examination_findings' => $data['examination_findings'] ?? null,
            'preliminary_diagnosis' => $data['preliminary_diagnosis'] ?? null,
            'medical_history' => $data['medical_history'] ?? null,
            'medication_history' => $data['medication_history'] ?? null,
            'severity_level' => $data['severity_level'] ?? null,
            'referring_person_name' => $data['referring_person_name'] ?? null,
            'referring_person_specialisation' => $data['referring_person_specialisation'] ?? null,
            'referring_person_cadre' => $data['referring_person_cadre'] ?? null,
            'contact_person_name' => $data['contact_person_name'] ?? null,
            'contact_person_phone' => $data['contact_person_phone'] ?? null,
            'contact_person_email' => $data['contact_person_email'] ?? null,
            'service_selection_type' => $data['service_selection_type'] ?? null,
            'service_bundle_id' => $data['service_bundle_id'] ?? null,
            'case_record_ids' => $data['case_record_ids'] ?? [],
            'status' => $data['status'] ?? 'PENDING',
            'utn' => $data['utn'] ?? $this->generateUTN(),
            'referral_code' => $data['referral_code'] ?? $this->generateReferralCode(),
            'valid_until' => now()->addMonths(3),
            'request_date' => now(),
            'created_by' => Auth::id(),
        ];

        return Referral::create($payload);
    }

    /**
     * Generate a simple UTN.
     */
    private function generateUTN(): string
    {
        return 'UTN-' . Str::upper(Str::random(10));
    }

    /**
     * Generate a fallback referral code.
     */
    private function generateReferralCode(): string
    {
        return 'REF-' . Str::upper(Str::random(8));
    }
}
