<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'enrollee_id' => $this->enrollee_id,
            'referring_facility_id' => $this->referring_facility_id,
            'receiving_facility_id' => $this->receiving_facility_id,
            'referral_code' => $this->referral_code,
            'utn' => $this->utn,
            'status' => $this->status,
            'severity_level' => $this->severity_level,
            'presenting_complains' => $this->presenting_complains,
            'reasons_for_referral' => $this->reasons_for_referral,
            'treatments_given' => $this->treatments_given,
            'investigations_done' => $this->investigations_done,
            'examination_findings' => $this->examination_findings,
            'preliminary_diagnosis' => $this->preliminary_diagnosis,
            'medical_history' => $this->medical_history,
            'medication_history' => $this->medication_history,
            'referring_person_name' => $this->referring_person_name,
            'referring_person_specialisation' => $this->referring_person_specialisation,
            'referring_person_cadre' => $this->referring_person_cadre,
            'contact_person_name' => $this->contact_person_name,
            'contact_person_phone' => $this->contact_person_phone,
            'contact_person_email' => $this->contact_person_email,
            'service_selection_type' => $this->service_selection_type,
            'service_bundle_id' => $this->service_bundle_id,
            'case_record_ids' => $this->case_record_ids ?? [],
            'requested_services' => $this->requested_services ?? [],
            'request_date' => $this->request_date,
            'approval_date' => $this->approval_date,
            'valid_until' => $this->valid_until,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'enrollee' => new EnrolleeResource($this->whenLoaded('enrollee')),
            'referring_facility' => new FacilityResource($this->whenLoaded('referringFacility')),
            'receiving_facility' => new FacilityResource($this->whenLoaded('receivingFacility')),
            'service_bundle' => $this->whenLoaded('serviceBundle', function () {
                return [
                    'id' => $this->serviceBundle->id,
                    'code' => $this->serviceBundle->code,
                    'name' => $this->serviceBundle->name,
                    'description' => $this->serviceBundle->description,
                    'fixed_price' => $this->serviceBundle->fixed_price,
                    'diagnosis_icd10' => $this->serviceBundle->diagnosis_icd10,
                ];
            }),
            'case_records' => !empty($this->case_record_ids) ? $this->caseRecords() : [],
        ];
    }
}
