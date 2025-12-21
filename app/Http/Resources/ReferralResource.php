<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\EnrolleeResource;
use App\Http\Resources\FacilityResource;

class ReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            // ... (All existing top-level fields)
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
            'valid_until' => $this->valid_until,
            'case_records' => $this->case_records_data,
            
            // ----------------------------------------------------
            // 1. Relationships using dedicated Resources
            // ----------------------------------------------------
            'enrollee' => new EnrolleeResource($this->whenLoaded('enrollee')),
            'referring_facility' => new FacilityResource($this->whenLoaded('referringFacility')),
            'receiving_facility' => new FacilityResource($this->whenLoaded('receivingFacility')),
            'utn_validated' => $this->utn_validated,
            'claim_submitted' => $this->claim_submitted,
            'claim_submitted_at' => $this->claim_submitted_at,
            'valid_until' => $this->valid_until,
            
            // ----------------------------------------------------
            // 2. Service Bundle (CaseRecord where is_bundle = true)
            // ----------------------------------------------------
            'service_bundle' => $this->whenLoaded('serviceBundle', function () {
                $bundle = $this->serviceBundle;
                return [
                    'id' => $bundle->id,
                    'code' => $bundle->nicare_code,
                    'name' => $bundle->case_name,
                    'description' => $bundle->service_description,
                    'fixed_price' => $bundle->bundle_price ?? $bundle->price,
                    'diagnosis_icd10' => $bundle->diagnosis_icd10,
                    'components' => $bundle->load('components')
                        ? $bundle->components->map(function ($component) {
                            return [
                                'id' => $component->id,
                                'case_record_id' => $component->case_record_id,
                                'quantity' => $component->quantity,
                                'max_quantity' => $component->max_quantity,
                                'item_name' => $component->item_name,
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
            
            // ----------------------------------------------------
            // 3. PA Codes (CRITICAL FIX: Removed manual database query)
            // ----------------------------------------------------
            'pa_codes' => $this->whenLoaded('paCodes', function () {
                // Collect all case record IDs from all PA codes to fetch in a single query
                $allCaseRecordIds = [];
                foreach ($this->paCodes as $pa) {
                    if (!empty($pa->case_record_ids) && is_array($pa->case_record_ids)) {
                        $allCaseRecordIds = array_merge($allCaseRecordIds, $pa->case_record_ids);
                    }
                }

                // Fetch all case records in a single query (prevents N+1)
                $caseRecordsById = [];
                if (!empty($allCaseRecordIds)) {
                    $caseRecords = \App\Models\CaseRecord::whereIn('id', array_unique($allCaseRecordIds))->get();
                    $caseRecordsById = $caseRecords->keyBy('id');
                }

                return $this->paCodes->map(function ($pa) use ($caseRecordsById) {

                    // Service Bundle Data (for PA-type Service Bundles)
                    // Bundle is now a CaseRecord where is_bundle = true
                    $serviceBundleData = null;
                    if ($pa->relationLoaded('serviceBundle') && $pa->serviceBundle) {
                        $bundle = $pa->serviceBundle;
                        $serviceBundleData = [
                            'id' => $bundle->id,
                            'name' => $bundle->case_name,
                            'description' => $bundle->service_description,
                            'fixed_price' => $bundle->bundle_price ?? $bundle->price,
                            'components' => []
                        ];

                        // Check if bundle components are loaded
                        if ($bundle->relationLoaded('bundleComponents') || $bundle->relationLoaded('components')) {
                            $components = $bundle->bundleComponents ?? $bundle->components;
                            $serviceBundleData['components'] = $components->map(function ($component) {
                                $caseRecordData = null;
                                if ($component->relationLoaded('caseRecord') && $component->caseRecord) {
                                    $caseRecordData = [
                                        'id' => $component->caseRecord->id,
                                        'nicare_code' => $component->caseRecord->nicare_code,
                                        'name' => $component->caseRecord->case_name,
                                        'price' => $component->caseRecord->price,
                                        'detail_type' => $component->caseRecord->detail_type,
                                    ];
                                }

                                return [
                                    'id' => $component->id,
                                    'case_record_id' => $component->case_record_id,
                                    'component_name' => $component->component_name ?? null,
                                    'item_name' => $component->item_name ?? $component->component_name,
                                    'description' => $component->description ?? null,
                                    'quantity' => $component->quantity,
                                    'unit_price' => $component->unit_price ?? 0,
                                    'max_quantity' => $component->max_quantity,
                                    'case_record' => $caseRecordData,
                                ];
                            })->toArray();
                        }
                    }

                    // Case Records Data (for PA-type FFS Case Records)
                    // Use the pre-fetched case records to avoid N+1 queries
                    $caseRecordsData = [];
                    if (!empty($pa->case_record_ids) && is_array($pa->case_record_ids)) {
                        foreach ($pa->case_record_ids as $caseRecordId) {
                            if (isset($caseRecordsById[$caseRecordId])) {
                                $cr = $caseRecordsById[$caseRecordId];
                                $caseRecordsData[] = [
                                    'id' => $cr->id,
                                    'nicare_code' => $cr->nicare_code,
                                    'case_name' => $cr->case_name,
                                    'service_description' => $cr->service_description,
                                    'price' => $cr->price,
                                    'detail_type' => $cr->detail_type,
                                ];
                            }
                        }
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
                        'case_records' => $caseRecordsData,
                    ];


                });
            }),
            
            // ... (admissions, case_records, documents, feedback_records mappings)
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
            // 'case_records' => $this->whenLoaded('caseRecords', function () {
            //       return $this->caseRecords->map(function ($caseRecord) {
            //          return [
            //            'id' => $caseRecord->id,
            //             'nicare_code' => $caseRecord->nicare_code,
            //             'case_name' => $caseRecord->case_name,
            //             'detail_type' => $caseRecord->detail_type,
            //          ];
            //      });
            // }),
           
            
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
                $user = auth()->user();
                $feedbackRecords = $this->feedbackRecords;

                // Filter feedback based on user role
                if ($user) {
                    $isAdmin = $user->hasAnyRole(['Super Admin', 'admin']);

                    if (!$isAdmin) {
                        // For facility users, only show:
                        // 1. Feedback created by users from their assigned facilities
                        // 2. System-generated feedback
                        $userFacilityIds = \App\Models\Facility::whereHas('assignedUsers', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        })->pluck('id')->toArray();

                        $feedbackRecords = $feedbackRecords->filter(function ($feedback) use ($userFacilityIds) {
                            // Always show system-generated feedback
                            if ($feedback->is_system_generated) {
                                return true;
                            }

                            // Show feedback created by users from the same facilities
                            if ($feedback->relationLoaded('creator') && $feedback->creator) {
                                $creatorFacilityIds = \App\Models\Facility::whereHas('assignedUsers', function ($query) use ($feedback) {
                                    $query->where('user_id', $feedback->creator->id);
                                })->pluck('id')->toArray();

                                // Check if there's any overlap between user's facilities and creator's facilities
                                return !empty(array_intersect($userFacilityIds, $creatorFacilityIds));
                            }

                            return false;
                        });
                    }
                }

                return $feedbackRecords->map(function ($feedback) {
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
                            'email' => $feedback->feedbackOfficer->email,
                        ] : null,
                        'creator' => $feedback->relationLoaded('creator') && $feedback->creator ? [
                            'id' => $feedback->creator->id,
                            'name' => $feedback->creator->name,
                            'email' => $feedback->creator->email,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}
