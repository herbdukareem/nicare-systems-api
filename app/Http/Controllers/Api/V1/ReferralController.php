<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\ReferralResource;
use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends BaseController
{
    private ReferralService $service;

    public function __construct(ReferralService $service)
    {
        $this->service = $service;
    }

    /**
     * List referrals with basic filtering.
     */
    public function index(Request $request)
    {
        $query = \App\Models\Referral::with(['enrollee', 'referringFacility', 'receivingFacility'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->severity_level, fn($q) => $q->where('severity_level', $request->severity_level))
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where('referral_code', 'like', "%{$search}%")
                    ->orWhere('utn', 'like', "%{$search}%")
                    ->orWhereHas('enrollee', function ($eq) use ($search) {
                        $eq->where('enrollee_id', 'like', "%{$search}%")
                           ->orWhere('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });

        $referrals = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return $this->sendResponse(
            ReferralResource::collection($referrals),
            'Referrals retrieved successfully'
        );
    }

    /**
     * Create a referral. Requested services are optional.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollee_id' => ['required', 'integer', 'exists:enrollees,id'],
            'referring_facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'receiving_facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'presenting_complains' => ['required', 'string'],
            'reasons_for_referral' => ['required', 'string'],
            'treatments_given' => ['required', 'string'],
            'investigations_done' => ['required', 'string'],
            'examination_findings' => ['required', 'string'],
            'preliminary_diagnosis' => ['required', 'string'],
            'medical_history' => ['nullable', 'string'],
            'medication_history' => ['nullable', 'string'],
            'severity_level' => ['required', 'string'],
            'referring_person_name' => ['required', 'string'],
            'referring_person_specialisation' => ['required', 'string'],
            'referring_person_cadre' => ['required', 'string'],
            'contact_person_name' => ['nullable', 'string'],
            'contact_person_phone' => ['nullable', 'string'],
            'contact_person_email' => ['nullable', 'email'],
            'service_selection_type' => ['nullable', 'in:bundle,direct'],
            'service_bundle_id' => ['nullable', 'required_if:service_selection_type,bundle', 'exists:service_bundles,id'],
            'case_record_id' => ['nullable', 'required_if:service_selection_type,direct', 'exists:case_records,id'],
        ]);

        $referral = $this->service->create($validated);

        return $this->sendResponse(
            new ReferralResource($referral->load(['enrollee', 'referringFacility', 'receivingFacility', 'serviceBundle', 'caseRecord'])),
            'Referral created successfully',
            201
        );
    }

    /**
     * Show a single referral.
     */
    public function show(\App\Models\Referral $referral)
    {
        $referral->load(['enrollee', 'referringFacility', 'receivingFacility', 'serviceBundle', 'caseRecord']);

        return $this->sendResponse(
            new ReferralResource($referral),
            'Referral retrieved successfully'
        );
    }

    /**
     * Approve a referral.
     */
    public function approve(\App\Models\Referral $referral)
    {
        if ($referral->status !== 'PENDING') {
            return $this->sendError('Only pending referrals can be approved', [], 400);
        }

        $referral->update([
            'status' => 'APPROVED',
            'approval_date' => now(),
        ]);

        return $this->sendResponse(
            new ReferralResource($referral->fresh(['enrollee', 'referringFacility', 'receivingFacility'])),
            'Referral approved successfully. UTN is now active.'
        );
    }

    /**
     * Reject a referral.
     */
    public function reject(Request $request, \App\Models\Referral $referral)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($referral->status !== 'PENDING') {
            return $this->sendError('Only pending referrals can be rejected', [], 400);
        }

        $referral->update([
            'status' => 'REJECTED',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return $this->sendResponse(
            new ReferralResource($referral->fresh(['enrollee', 'referringFacility', 'receivingFacility'])),
            'Referral rejected successfully'
        );
    }
}
