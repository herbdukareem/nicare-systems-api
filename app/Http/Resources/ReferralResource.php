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
            'case_record_ids' => is_array($this->case_record_ids)
                ? $this->case_record_ids
                : (json_decode($this->case_record_ids, true) ?: []),
            'requested_services' => $this->requested_services ?? [],
            'request_date' => $this->request_date,
            'approval_date' => $this->approval_date,
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
            'case_record' => $this->whenLoaded('caseRecord', function () {
                return [
                    'id' => $this->caseRecord->id,
                    'nicare_code' => $this->caseRecord->nicare_code,
                    'case_name' => $this->caseRecord->case_name,
                    'detail_type' => $this->caseRecord->detail_type,
                ];
            }),
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path,
                        'file_type' => $doc->file_type,
                        'file_size' => $doc->file_size,
                        'file_size_human' => $doc->file_size_human,
                        'mime_type' => $doc->mime_type,
                        'original_filename' => $doc->original_filename,
                        'url' => $doc->url,
                        'is_required' => $doc->is_required,
                        'is_validated' => $doc->is_validated,
                        'validated_at' => $doc->validated_at,
                        'uploaded_by' => $doc->uploaded_by,
                        'created_at' => $doc->created_at,
                        'document_requirement' => $doc->relationLoaded('documentRequirement') && $doc->documentRequirement ? [
                            'id' => $doc->documentRequirement->id,
                            'name' => $doc->documentRequirement->name,
                            'description' => $doc->documentRequirement->description,
                        ] : null,
                        'uploader' => $doc->relationLoaded('uploader') && $doc->uploader ? [
                            'id' => $doc->uploader->id,
                            'name' => $doc->uploader->name,
                            'email' => $doc->uploader->email,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
