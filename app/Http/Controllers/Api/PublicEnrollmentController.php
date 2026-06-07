<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\EnrolleeResource;
use App\Http\Resources\PremiumPlanResource;
use App\Models\Facility;
use App\Models\InsuranceProgramme;
use App\Models\Lga;
use App\Models\PremiumPlan;
use App\Models\Ward;
use App\Services\PublicEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublicEnrollmentController extends BaseController
{
    public function metadata(Request $request)
    {
        $programmeId = $request->integer('insurance_programme_id') ?: null;
        $lgaId = $request->integer('lga_id') ?: null;
        $wardId = $request->integer('ward_id') ?: null;

        $plansQuery = PremiumPlan::with(['programme', 'benefitPackage'])
            ->where('status', 'active');

        if (Schema::hasColumn('premium_plans', 'self_enrollment_enabled')) {
            $plansQuery->where('self_enrollment_enabled', true);
        }

        if ($programmeId) {
            $plansQuery->where('insurance_programme_id', $programmeId);
        }

        $plans = $plansQuery
            ->orderBy('amount')
            ->orderBy('name')
            ->get();

        $programmeIds = $plans->pluck('insurance_programme_id')->unique()->values();

        return $this->sendResponse([
            'insurance_programmes' => InsuranceProgramme::whereIn('id', $programmeIds)->orderBy('name')->get(),
            'premium_plans' => PremiumPlanResource::collection($plans),
            'lgas' => Lga::orderBy('name')->get(),
            'wards' => Ward::query()
                ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
                ->orderBy('name')
                ->get(),
            'facilities' => Facility::query()
                ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
                ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
                ->where('status', 1)
                ->orderBy('name')
                ->get(),
        ], 'Public enrollment metadata retrieved successfully.');
    }

    public function store(Request $request, PublicEnrollmentService $service)
    {
        $validated = $request->validate([
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'nin' => ['nullable', 'string', 'max:255', 'unique:enrollees,nin'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:enrollees,email'],
            'phone' => ['required', 'string', 'max:255', 'unique:enrollees,phone'],
            'date_of_birth' => ['required', 'date'],
            'sex' => ['required', 'integer', Rule::in([1, 2])],
            'marital_status' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'address' => ['nullable', 'string'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'lga_id' => ['required', 'exists:lgas,id'],
            'ward_id' => ['required', 'exists:wards,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $service->submitApplication($validated);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse([
            'enrollee' => new EnrolleeResource($result['enrollee']),
            'purchase' => $result['purchase'],
            'requires_payment' => $result['requires_payment'],
            'next_steps' => $result['next_steps'],
        ], 'Self-enrollment application submitted successfully.', 201);
    }
}
