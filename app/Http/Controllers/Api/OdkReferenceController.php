<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\InsuranceProgramme;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdkReferenceController extends Controller
{
    public function facilities(Request $request): JsonResponse
    {
        $this->authorizeReferenceRequest($request);

        $facilities = Facility::query()
            ->with(['lga:id,name,code,zone,status', 'ward:id,name,lga_id,settlement_type,status'])
            ->when($request->boolean('active_only', true), function ($query): void {
                $query->where('status', 1)
                    ->where('accreditation_status', 'active');
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('lga_id'), fn ($query) => $query->where('lga_id', $request->integer('lga_id')))
            ->when($request->filled('ward_id'), fn ($query) => $query->where('ward_id', $request->integer('ward_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('hcp_code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        $facilities->getCollection()->transform(fn (Facility $facility): array => [
            'id' => $facility->id,
            'hcp_code' => $facility->hcp_code,
            'code' => $facility->hcp_code,
            'name' => $facility->name,
            'ownership' => $facility->ownership,
            'category' => $facility->ownership,
            'type' => $facility->type,
            'level_of_care' => $facility->level_of_care,
            'address' => $facility->address,
            'phone' => $facility->phone,
            'email' => $facility->email,
            'lga_id' => $facility->lga_id,
            'ward_id' => $facility->ward_id,
            'lga' => $facility->lga ? [
                'id' => $facility->lga->id,
                'code' => $facility->lga->code,
                'name' => $facility->lga->name,
                'zone' => $facility->lga->zone,
                'status' => $facility->lga->status,
            ] : null,
            'ward' => $facility->ward ? [
                'id' => $facility->ward->id,
                'name' => $facility->ward->name,
                'lga_id' => $facility->ward->lga_id,
                'settlement_type' => $facility->ward->settlement_type,
                'status' => $facility->ward->status,
            ] : null,
            'status' => $facility->status,
            'accreditation_status' => $facility->accreditation_status ?? 'active',
            'updated_at' => $facility->updated_at?->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $facilities,
        ]);
    }

    public function lgas(Request $request): JsonResponse
    {
        $this->authorizeReferenceRequest($request);

        $lgas = Lga::query()
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('status', 1))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'data' => $lgas,
        ]);
    }

    public function wards(Request $request): JsonResponse
    {
        $this->authorizeReferenceRequest($request);

        $wards = Ward::query()
            ->with('lga:id,name,code,zone,status')
            ->when($request->boolean('active_only', true), fn ($query) => $query->where('status', 1))
            ->when($request->filled('lga_id'), fn ($query) => $query->where('lga_id', $request->integer('lga_id')))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'data' => $wards,
        ]);
    }

    public function premiumMetadata(Request $request): JsonResponse
    {
        $this->authorizeReferenceRequest($request);

        return response()->json([
            'success' => true,
            'data' => [
                'insurance_programmes' => InsuranceProgramme::query()
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'status', 'updated_at']),
            ],
        ]);
    }

    private function authorizeReferenceRequest(Request $request): void
    {
        $configuredToken = trim((string) config('services.odk_reference.token'));
        abort_if($configuredToken === '', 503, 'ODK reference export token is not configured.');

        $requestToken = $request->bearerToken() ?: $request->header('X-NICARE-ODK-TOKEN', '');
        abort_unless(hash_equals($configuredToken, (string) $requestToken), 401, 'Invalid ODK reference export token.');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 500), 1), 1000);
    }
}
