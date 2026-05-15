<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreLgaRequest;
use App\Http\Requests\UpdateLgaRequest;
use App\Http\Resources\LgaResource;
use App\Http\Resources\WardResource;
use App\Models\Lga;
use App\Services\LgaService;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for LGAs.
 */
class LgaController extends BaseController
{
    protected LgaService $service;

    public function __construct(LgaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $query = Lga::query()->withCount(['wards', 'facilities', 'enrollees']);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $lgas = $query->orderBy('name')->paginate((int) $request->get('per_page', 50));
        $response = LgaResource::collection($lgas);
        $response->additional(['meta' => [
            'total' => $lgas->total(),
            'per_page' => $lgas->perPage(),
            'current_page' => $lgas->currentPage(),
            'last_page' => $lgas->lastPage(),
        ]]);

        return $this->sendResponse($response, 'LGAs retrieved successfully');
    }

    public function store(StoreLgaRequest $request)
    {
        $lga = $this->service->create($request->validated());
        return $this->sendResponse(new LgaResource($lga), 'LGA created successfully', 201);
    }

    public function show(Lga $lga)
    {
        return $this->sendResponse(new LgaResource($lga->loadCount(['wards', 'facilities', 'enrollees'])), 'LGA retrieved successfully');
    }

    public function update(UpdateLgaRequest $request, Lga $lga)
    {
        $lga = $this->service->update($lga, $request->validated());
        return $this->sendResponse(new LgaResource($lga), 'LGA updated successfully');
    }

    public function destroy(Lga $lga)
    {
        if ($lga->wards()->exists() || $lga->facilities()->exists() || $lga->enrollees()->exists()) {
            $lga->update(['status' => 0]);
            return $this->sendResponse(new LgaResource($lga), 'LGA is in use and was deactivated instead.');
        }

        $this->service->delete($lga);
        return $this->sendResponse([], 'LGA deleted successfully');
    }

    public function wards(Lga $lga)
    {
        return $this->sendResponse(WardResource::collection($lga->wards()->with('lga')->orderBy('name')->get()), 'LGA wards retrieved successfully');
    }
}
