<?php

namespace App\Http\Resources;

use App\Models\CaseRecord;
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
            'utn_validated' => $this->utn_validated,
            'claim_submitted' => $this->claim_submitted,
            'claim_submitted_at' => $this->claim_submitted_at,
            'valid_until' => $this->valid_until,
            'service_bundle' => $this->whenLoaded('serviceBundle', function () {
                $bundle = $this->serviceBundle;
                return [
                    'id' => $bundle->id,
                    'code' => $bundle->code,
                    'name' => $bundle->name,
                    'description' => $bundle->description,
                    'fixed_price' => $bundle->fixed_price,
                    'diagnosis_icd10' => $bundle->diagnosis_icd10,
                    'components' => $bundle->relationLoaded('components')
                        ? $bundle->components->map(function ($component) {
                            return [
                                'id' => $component->id,
                                'case_record_id' => $component->case_record_id,
                                'quantity' => $component->quantity,
                                'max_quantity' => $component->max_quantity,
                                'case_record' => $component->relationLoaded('caseRecord') && $component->caseRecord ? [
                                    'id' => $component->caseRecord->id,
                                    'nicare_code' => $component->caseRecord->nicare_code,
                                    'case_name' => $component->caseRecord->case_name,
                                    'price' => $component->caseRecord->price,
                                    'detail_type' => $component->caseRecord->detail_type,
                                ] : null,
                            ];
                        })
                        : [],
                ];
            }),
            'pa_codes' => $this->whenLoaded('paCodes', function () {
                return $this->paCodes->map(function ($pa) {
                    $serviceBundleData = null;
                    $caseRecordData = null;
                    $caseRecords = [];
                    // fix ($json) must be of type string, array given, null given
                    $caseRecordIds = $pa->case_record_ids  ?? [];
                    if (!empty($caseRecordIds)) {
                        $caseRecords = CaseRecord::whereIn('id', $caseRecordIds)->get();
                        $caseRecordData = $caseRecords->map(function ($caseRecord) {
                            return [
                                'id' => $caseRecord->id,
                                'nicare_code' => $caseRecord->nicare_code,
                                'case_name' => $caseRecord->case_name,
                                'price' => $caseRecord->price,
                                'detail_type' => $caseRecord->detail_type,
                            ];
                        });
                    }
                     
                    
                    if ($pa->relationLoaded('serviceBundle') && $pa->serviceBundle) {
                        $bundle = $pa->serviceBundle;
                        $serviceBundleData = [
                            'id' => $bundle->id,
                            'name' => $bundle->name,
                            'description' => $bundle->description,
                            'fixed_price' => $bundle->fixed_price,
                            'components' => $bundle->relationLoaded('components')
                                ? $bundle->components->map(function ($component) {
                                    return [
                                        'id' => $component->id,
                                        'case_record_id' => $component->case_record_id,
                                        'component_name' => $component->component_name ?? null,
                                        'description' => $component->description ?? null,
                                        'quantity' => $component->quantity,
                                        'unit_price' => $component->unit_price ?? 0,
                                        'max_quantity' => $component->max_quantity,
                                        'item_type' => $component->item_type ?? null,
                                        'case_record' => $component->relationLoaded('caseRecord') && $component->caseRecord ? [
                                            'id' => $component->caseRecord->id,
                                            'nicare_code' => $component->caseRecord->nicare_code,
                                            'name' => $component->caseRecord->case_name,
                                            'price' => $component->caseRecord->price,
                                            'detail_type' => $component->caseRecord->detail_type,
                                        ] : null,
                                    ];
                                })
                                : [],
                        ];
                    }
                    return [
                        'id' => $pa->id,
                        'code' => $pa->code,
                        'type' => $pa->type,
                        'status' => $pa->status,
                        'case_record_ids' => $pa->case_record_ids,
                        'justification' => $pa->justification ?? null,
                        'service_bundle_id' => $pa->service_bundle_id,
                        'service_bundle' => $serviceBundleData,
                        'case_records' => $caseRecordData,
                    ];
                });
            }),
            'admissions' => $this->whenLoaded('admissions', function () {
                return $this->admissions->map(function ($admission) {
                    return [
                        'id' => $admission->id,
                        'admission_number' => $admission->admission_number,
                        'admission_date' => $admission->admission_date,
                        'discharge_date' => $admission->discharge_date,
                        'status' => $admission->status,
                    ];
                });
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
            'feedback_records' => $this->whenLoaded('feedbackRecords', function () {
                return $this->feedbackRecords->map(function ($feedback) {
                    return [
                        'id' => $feedback->id,
                        'feedback_code' => $feedback->feedback_code,
                        'feedback_type' => $feedback->feedback_type,
                        'event_type' => $feedback->event_type,
                        'is_system_generated' => $feedback->is_system_generated,
                        'status' => $feedback->status,
                        'priority' => $feedback->priority,
                        'feedback_comments' => $feedback->feedback_comments,
                        'officer_observations' => $feedback->officer_observations,
                        'referral_status_before' => $feedback->referral_status_before,
                        'referral_status_after' => $feedback->referral_status_after,
                        'feedback_date' => $feedback->feedback_date,
                        'created_at' => $feedback->created_at,
                        'feedback_officer' => $feedback->relationLoaded('feedbackOfficer') && $feedback->feedbackOfficer ? [
                            'id' => $feedback->feedbackOfficer->id,
                            'name' => $feedback->feedbackOfficer->name,
                        ] : null,
                        'creator' => $feedback->relationLoaded('creator') && $feedback->creator ? [
                            'id' => $feedback->creator->id,
                            'name' => $feedback->creator->name,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
