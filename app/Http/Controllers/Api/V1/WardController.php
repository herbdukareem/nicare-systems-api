<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreWardRequest;
use App\Http\Requests\UpdateWardRequest;
use App\Http\Resources\WardResource;
use App\Models\Ward;
use App\Services\WardService;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for wards.
 */
class WardController extends BaseController
{
    protected WardService $service;

    public function __construct(WardService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = Ward::query()->with('lga')->withCount(['facilities', 'enrollees']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('lga_id')) {
            $query->where('lga_id', $request->integer('lga_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $wards = $query->orderBy('name')->paginate((int) $request->get('per_page', 50));
        $response = WardResource::collection($wards);
        $response->additional(['meta' => [
            'total' => $wards->total(),
            'per_page' => $wards->perPage(),
            'current_page' => $wards->currentPage(),
            'last_page' => $wards->lastPage(),
        ]]);

        return $this->sendResponse($response, 'Wards retrieved successfully');
    }

    public function store(StoreWardRequest $request)
    {
        $ward = $this->service->create($request->validated());
        return $this->sendResponse(new WardResource($ward), 'Ward created successfully', 201);
    }

    public function show(Ward $ward)
    {
        $ward->load('lga')->loadCount(['facilities', 'enrollees']);
        return $this->sendResponse(new WardResource($ward), 'Ward retrieved successfully');
    }

    public function update(UpdateWardRequest $request, Ward $ward)
    {
        $ward = $this->service->update($ward, $request->validated());
        return $this->sendResponse(new WardResource($ward), 'Ward updated successfully');
    }

    public function destroy(Ward $ward)
    {
        if ($ward->facilities()->exists() || $ward->enrollees()->exists()) {
            $ward->update(['status' => 0]);
            return $this->sendResponse(new WardResource($ward->load('lga')), 'Ward is in use and was deactivated instead.');
        }

        $this->service->delete($ward);
        return $this->sendResponse([], 'Ward deleted successfully');
    }
}
