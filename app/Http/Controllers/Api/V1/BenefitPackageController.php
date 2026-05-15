<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\BenefitPackageResource;
use App\Models\BenefitPackage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class BenefitPackageController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = BenefitPackage::query()->withCount(['premiumPlans', 'enrollees']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $packages = $query
            ->orderBy($request->get('sort_by', 'name'), $request->get('sort_direction', 'asc'))
            ->paginate((int) $request->get('per_page', 25));

        $response = BenefitPackageResource::collection($packages);
        $response->additional(['meta' => [
            'total' => $packages->total(),
            'per_page' => $packages->perPage(),
            'current_page' => $packages->currentPage(),
            'last_page' => $packages->lastPage(),
        ]]);

        return $this->sendResponse($response, 'Benefit packages retrieved successfully');
    }

    public function all(): JsonResponse
    {
        return $this->sendResponse(BenefitPackageResource::collection(BenefitPackage::active()->orderBy('name')->get()), 'Benefit packages retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:benefit_packages,code'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $data['status'] = $data['status'] ?? 1;
        $package = BenefitPackage::create($data);

        return $this->sendResponse(new BenefitPackageResource($package), 'Benefit package created successfully', 201);
    }

    public function show(BenefitPackage $benefitPackage): JsonResponse
    {
        return $this->sendResponse(new BenefitPackageResource($benefitPackage->loadCount(['premiumPlans', 'enrollees'])), 'Benefit package retrieved successfully');
    }

    public function update(Request $request, BenefitPackage $benefitPackage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('benefit_packages', 'code')->ignore($benefitPackage)],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $benefitPackage->update($data);

        return $this->sendResponse(new BenefitPackageResource($benefitPackage->fresh()), 'Benefit package updated successfully');
    }

    public function destroy(BenefitPackage $benefitPackage): JsonResponse
    {
        if ($benefitPackage->premiumPlans()->exists() || $benefitPackage->enrollees()->exists()) {
            $benefitPackage->update(['status' => 0]);
            return $this->sendResponse(new BenefitPackageResource($benefitPackage), 'Benefit package is in use and was deactivated instead.');
        }

        $benefitPackage->delete();
        return $this->sendResponse([], 'Benefit package deleted successfully');
    }

    public function dropdown(): JsonResponse
    {
        return $this->all();
    }

    public function toggleStatus(BenefitPackage $benefitPackage): JsonResponse
    {
        $benefitPackage->update(['status' => (int) ! (bool) $benefitPackage->status]);
        return $this->sendResponse(new BenefitPackageResource($benefitPackage), 'Benefit package status updated successfully');
    }
}
