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
            // 2. Service Bundle (Mapping the loaded components)
            // ----------------------------------------------------
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
            
            // ----------------------------------------------------
            // 3. PA Codes (CRITICAL FIX: Removed manual database query)
            // ----------------------------------------------------
            'pa_codes' => $this->whenLoaded('paCodes', function () {
                return $this->paCodes->map(function ($pa) {
                    
                    // Service Bundle Data (for PA-type Service Bundles)
                    $serviceBundleData = $pa->whenLoaded('serviceBundle', function () use ($pa) {
                        $bundle = $pa->serviceBundle;
                        return [
                            'id' => $bundle->id,
                            'name' => $bundle->name,
                            'description' => $bundle->description,
                            'fixed_price' => $bundle->fixed_price,
                            'components' => $bundle->whenLoaded('components', function () use ($bundle) {
                                return $bundle->components->map(function ($component) {
                                    // Use component mapping logic as above
                                    return [
                                        'id' => $component->id,
                                        'case_record_id' => $component->case_record_id,
                                        'component_name' => $component->component_name ?? null,
                                        'description' => $component->description ?? null,
                                        'quantity' => $component->quantity,
                                        'unit_price' => $component->unit_price ?? 0,
                                        'max_quantity' => $component->max_quantity,
                                        'item_type' => $component->item_type ?? null,
                                        'case_record' => $component->whenLoaded('caseRecord', function () use ($component) {
                                            return $component->caseRecord ? [
                                                'id' => $component->caseRecord->id,
                                                'nicare_code' => $component->caseRecord->nicare_code,
                                                'name' => $component->caseRecord->case_name, // Note: using 'name' vs 'case_name'
                                                'price' => $component->caseRecord->price,
                                                'detail_type' => $component->caseRecord->detail_type,
                                            ] : null;
                                        }),
                                    ];
                                });
                            }),
                        ];
                    });

                    // Case Records Data (for PA-type FFS Case Records)
                    // The manual query has been removed. 
                    // To include FFS CaseRecords, the Referral model should have a relationship 
                    // (e.g., hasManyThrough or a dedicated pivot) that is eager-loaded.
                    // Since that's not possible from an array of IDs, we only include the IDs here.
                    // **Note: The consuming front-end should primarily rely on the Claim's
                    // `claimLineItems` and `claimPACodes` for FFS details.**
                    
                    return [
                        'id' => $pa->id,
                        'code' => $pa->code,
                        'type' => $pa->type,
                        'status' => $pa->status,
                        'case_record_ids' => $pa->case_record_ids,
                        'justification' => $pa->justification ?? null,
                        'service_bundle_id' => $pa->service_bundle_id,
                        'service_bundle' => $serviceBundleData,
                        // 'case_records' is intentionally removed/set to null to prevent N+1 queries. 
                        // If this data is essential, a proper many-to-many relationship must be defined 
                        // and eager-loaded in the controller instead of querying here.
                        'case_records' => null, 
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
