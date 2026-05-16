<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CapitationComputationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CapitationBatchRequest;
use App\Models\Capitation;
use App\Models\Facility;
use App\Services\CapitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CapitationController extends Controller
{
    public function __construct(private readonly CapitationService $service)
    {
    }

    /**
     * GET /api/capitation/periods
     * List all capitation periods with pagination and optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'year', 'month', 'funding_type_id', 'user_id', 'sort_by', 'sort_direction', 'per_page', 'page']);
            $periods = $this->service->getAll($filters);

            return response()->json([
                'success' => true,
                'data'    => $periods,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /api/capitation/periods
     * Create a new capitation period batch.
     */
    public function store(CapitationBatchRequest $request): JsonResponse
    {
        try {
            $capitation = $this->service->createPeriod($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Capitation period created successfully.',
                'data'    => $capitation,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /api/capitation/periods/{capitation}
     */
    public function show(Capitation $capitation): JsonResponse
    {
        try {
            $capitation->load(['user', 'fundingType', 'capitationDetails.facility', 'capitationDetails.fundingType', 'capitationPayments']);

            return response()->json([
                'success' => true,
                'data'    => $capitation,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /api/capitation/periods/{capitation}/compute
     * Trigger BR-07 compliant computation for the period.
     */
    public function compute(Capitation $capitation): JsonResponse
    {
        $validated = request()->validate([
            'funding_type_id' => ['required', 'integer', 'exists:funding_types,id'],
            'facility_ids' => ['required', 'array', 'min:1'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ]);

        try {
            if ($capitation->status) {
                return $this->error('Cannot compute a finalised capitation period.', 422);
            }

            $results = $this->service->computeForPeriod(
                $capitation,
                (int) $validated['funding_type_id'],
                $validated['facility_ids'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Capitation computed successfully.',
                'data'    => $results,
            ]);
        } catch (CapitationComputationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /api/capitation/periods/{capitation}/breakdown
     * Return per-facility breakdown.
     */
    public function breakdown(Capitation $capitation): JsonResponse
    {
        $validated = request()->validate([
            'stage' => ['nullable', 'in:generated,reviewed,approved,paid'],
        ]);

        try {
            $details = $this->service->getBreakdown($capitation, $validated['stage'] ?? 'generated');

            return response()->json([
                'success' => true,
                'data'    => $details,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /api/capitation/periods/{capitation}/eligible-providers
     * Preview providers eligible under the selected funding type before generation.
     */
    public function eligibleProviders(Capitation $capitation): JsonResponse
    {
        $validated = request()->validate([
            'funding_type_id' => ['required', 'integer', 'exists:funding_types,id'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->eligibleProvidersForPeriod($capitation, (int) $validated['funding_type_id']),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function details(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['nullable', 'in:generated,review,approval,payment,paid'],
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->getDetailsForStage(
                    $capitation,
                    $validated['stage'] ?? 'generated',
                    isset($validated['funding_type_id']) ? (int) $validated['funding_type_id'] : null
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function reviewDetails(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'detail_ids' => ['required', 'array', 'min:1'],
            'detail_ids.*' => ['integer', 'exists:capitation_details,id'],
        ]);

        try {
            $count = $this->service->reviewDetails($capitation, $validated['detail_ids']);

            return response()->json([
                'success' => true,
                'message' => "{$count} capitation detail(s) reviewed successfully.",
                'data' => ['count' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function approveDetails(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'detail_ids' => ['required', 'array', 'min:1'],
            'detail_ids.*' => ['integer', 'exists:capitation_details,id'],
        ]);

        try {
            $count = $this->service->approveDetails($capitation, $validated['detail_ids']);

            return response()->json([
                'success' => true,
                'message' => "{$count} capitation detail(s) approved successfully.",
                'data' => ['count' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function payDetails(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'detail_ids' => ['required', 'array', 'min:1'],
            'detail_ids.*' => ['integer', 'exists:capitation_details,id'],
            'payment_reference' => ['required', 'string', 'max:120'],
            'payment_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $count = $this->service->payDetails($capitation, $validated['detail_ids'], $validated);

            return response()->json([
                'success' => true,
                'message' => "{$count} capitation detail(s) paid successfully.",
                'data' => ['count' => $count],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /api/capitation/periods/{capitation}/finalise
     * BR-06: finaliser !== creator. BR-09: writes audit trail.
     */
    public function finalise(Capitation $capitation): JsonResponse
    {
        try {
            $capitation = $this->service->finalise($capitation);

            return response()->json([
                'success' => true,
                'message' => 'Capitation period finalised successfully.',
                'data'    => $capitation,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /api/capitation/periods/{capitation}/pay
     * Confirm payment for a finalised capitation period.
     */
    public function pay(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'payment_reference' => ['required', 'string', 'max:120'],
            'payment_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $capitation = $this->service->markPaid($capitation, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Capitation payment confirmed successfully.',
                'data'    => $capitation,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /api/capitation/periods/{capitation}/export
     * Export capitation breakdown as CSV.
     */
    public function export(Capitation $capitation): StreamedResponse
    {
        $details = $this->service->getBreakdown($capitation);

        return response()->streamDownload(function () use ($capitation, $details) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Facility', 'Enrollee Count', 'Rate (NGN)', 'Total Amount (NGN)', 'Period']);

            foreach ($details as $d) {
                fputcsv($out, [
                    $d->facility->name ?? 'N/A',
                    $d->total_enrollees ?? 0,
                    number_format((float) ($d->capitation_rate ?? 0), 2),
                    number_format((float) ($d->total_amount ?? 0), 2),
                    $capitation->name,
                ]);
            }
            fclose($out);
        }, 'capitation_' . $capitation->id . '_' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * GET /api/capitation/facilities/{facility}/capitation-history
     */
    public function facilityHistory(Facility $facility): JsonResponse
    {
        try {
            $history = $this->service->getFacilityHistory($facility->id);

            return response()->json([
                'success' => true,
                'data'    => $history,
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------

    private function error(string $message, int $status = 500): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
