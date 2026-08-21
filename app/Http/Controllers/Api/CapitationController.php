<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\CapitationComputationException;
use App\Exports\CapitationBreakdownExport;
use App\Exports\CapitationEnrolleeListExport;
use App\Exports\CapitationPaymentReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CapitationBatchRequest;
use App\Models\Capitation;
use App\Models\CapitationDetailEnrollee;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\FundingType;
use App\Services\CapitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
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
            $periods->setCollection(
                $periods->getCollection()->map(fn (Capitation $period) => $this->serializePeriod($period))
            );

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

    private function serializePeriod(Capitation $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'year' => (int) $period->year,
            'capitation_month' => $period->capitation_month !== null ? (int) $period->capitation_month : null,
            'capitated_month' => $period->capitated_month !== null ? (int) $period->capitated_month : null,
            'period_start' => $period->period_start,
            'period_end' => $period->period_end,
            'capitation_rate' => $period->capitation_rate !== null ? (float) $period->capitation_rate : null,
            'status' => (bool) $period->status,
            'funding_type_id' => $period->funding_type_id !== null ? (int) $period->funding_type_id : null,
            'funding_type' => $period->fundingType ? [
                'id' => $period->fundingType->id,
                'name' => $period->fundingType->name,
            ] : null,
            'funding_types' => $period->getAttribute('funding_types') ?? [],
            'funding_type_summary' => $period->getAttribute('funding_type_summary'),
            'capitation_details_count' => (int) ($period->capitation_details_count ?? 0),
            'pending_review_count' => (int) ($period->pending_review_count ?? 0),
            'reviewed_count' => (int) ($period->reviewed_count ?? 0),
            'pending_approval_count' => (int) ($period->pending_approval_count ?? 0),
            'approved_count' => (int) ($period->approved_count ?? 0),
            'pending_payment_count' => (int) ($period->pending_payment_count ?? 0),
            'paid_count' => (int) ($period->paid_count ?? 0),
            'computed_at' => $period->computed_at,
            'finalised_at' => $period->finalised_at,
            'created_at' => $period->created_at,
            'updated_at' => $period->updated_at,
        ];
    }

    /**
     * POST /api/capitation/periods/{capitation}/compute
     * Trigger BR-07 compliant computation for the period.
     */
    public function compute(Capitation $capitation): JsonResponse
    {
        $validated = request()->validate([
            'funding_type_id' => ['required', 'integer', 'exists:funding_types,id'],
            'duplicate_nin_policy' => ['required', 'in:exclude,include'],
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
                (string) $validated['duplicate_nin_policy'],
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
            'duplicate_nin_policy' => ['required', 'in:exclude,include'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->eligibleProvidersForPeriod(
                    $capitation,
                    (int) $validated['funding_type_id'],
                    (string) $validated['duplicate_nin_policy']
                ),
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
     * Export the selected funding type's capitation payment details as Excel.
     */
    public function exportBreakdownExcel(Request $request, Capitation $capitation)
    {
        $validated = $request->validate([
            'stage' => ['nullable', 'in:generated,reviewed,approved,paid'],
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
        ]);

        $fundingTypeId = isset($validated['funding_type_id']) ? (int) $validated['funding_type_id'] : null;
        $details = $this->service->getBreakdown(
            $capitation,
            $validated['stage'] ?? 'generated',
            $fundingTypeId,
        );
        $fundingTypeLabel = $fundingTypeId
            ? FundingType::findOrFail($fundingTypeId)->name
            : 'All Funding Types';
        $filenameSuffix = $fundingTypeId ? '_funding_type_' . $fundingTypeId : '_all_funding_types';

        return Excel::download(
            new CapitationBreakdownExport($capitation, $details, $fundingTypeLabel),
            'capitation_payment_details_' . $capitation->id . $filenameSuffix . '.xlsx'
        );
    }

    public function exportPaymentReport(Request $request, Capitation $capitation)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:all,generated,reviewed,approved,paid'],
        ]);

        $status = (string) $validated['status'];
        $statusLabel = ucfirst($status === 'all' ? 'All statuses' : $status);
        $rows = $this->service->getPaymentReport($capitation, $status);

        return Excel::download(
            new CapitationPaymentReportExport($capitation, $rows, $statusLabel),
            'capitation_payment_report_' . $capitation->id . '_' . $status . '.xlsx'
        );
    }

    public function enrolleeList(Request $request, Capitation $capitation): JsonResponse
    {
        $validated = $request->validate([
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            $snapshots = $this->service->getEnrolleeSnapshotList($capitation, $validated);
            $snapshots->setCollection(
                $snapshots->getCollection()->map(
                    fn (CapitationDetailEnrollee $snapshot) => $this->serializeEnrolleeSnapshot($snapshot)
                )
            );

            return response()->json([
                'success' => true,
                'data' => $snapshots,
                'summary' => $this->service->getEnrolleeSnapshotSummary($capitation, $validated),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    public function exportEnrolleeList(Request $request, Capitation $capitation)
    {
        $validated = $request->validate([
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = $this->service->getEnrolleeSnapshotExportQuery($capitation, $validated);

        return Excel::download(
            new CapitationEnrolleeListExport($capitation, $query),
            'capitation_enrollee_list_' . $capitation->id . '_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export the bank-upload spreadsheet expected by the legacy Remita process.
     */
    public function exportRemita(Capitation $capitation): StreamedResponse
    {
        $totalDetails = $capitation->capitationDetails()->count();
        $details = $this->service->getBreakdown($capitation, 'paid');

        if ($totalDetails === 0 || $details->count() !== $totalDetails) {
            abort(422, 'The Remita payment format is available only after all capitation details have been marked paid.');
        }

        $year = (string) ($capitation->year ?: substr((string) $capitation->period_start, 0, 4));
        $shortDescription = strtoupper(substr((string) $capitation->name, 0, 2) . substr($year, -2) . 'CAP');
        $longDescription = strtoupper(trim("{$capitation->name} {$year}"));

        return response()->streamDownload(function () use ($details, $shortDescription, $longDescription) {
            $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $textCell = static fn (mixed $value): string => '<td style="mso-number-format:\'\\@\';">&nbsp;' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';

            // Remita uses the legacy 13-column arrangement. Excel must receive
            // sort codes and account numbers as text so it cannot strip leading zeroes.
            echo "\xEF\xBB\xBF<html><head><meta charset=\"UTF-8\"></head><body><table border=\"1\">";
            echo '<tr><th>SN</th><th>SORT CODE</th><th>ACCT. NUMBER</th><th></th><th>NO. ENROLLEES</th><th>AMOUNT</th>'
                . '<th>DESCRIPTION 1</th><th>DESCRIPTION 2</th><th>ACCOUNT NAME</th><th></th><th></th><th></th><th></th></tr>';

            foreach ($details->values() as $index => $detail) {
                $account = $detail->facility?->accountDetail;
                $bank = $account?->bank;

                $accountName = preg_replace('/[^a-zA-Z0-9_ -]/', ' ', (string) $account?->account_name) ?? '';
                echo '<tr>'
                    . '<td>' . ($index + 1) . '</td>'
                    . $textCell($bank?->sort_code)
                    . $textCell($account?->account_number)
                    . '<td></td>'
                    . '<td>' . (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0) . '</td>'
                    . '<td>' . number_format((float) ($detail->total_amount ?? $detail->amount ?? 0), 2, '.', '') . '</td>'
                    . '<td>' . $escape($shortDescription) . '</td>'
                    . '<td>' . $escape($longDescription) . '</td>'
                    . '<td>' . $escape($accountName) . '</td>'
                    . '<td>0</td><td>0</td><td>0</td><td>0</td>'
                    . '</tr>';
            }

            echo '</table></body></html>';
        }, 'NiCare_Cap_Payment_Remita_Format_' . $capitation->id . '.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
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

    private function serializeEnrolleeSnapshot(CapitationDetailEnrollee $snapshot): array
    {
        return [
            'id' => $snapshot->id,
            'enrollee_id' => $snapshot->enrollee_id,
            'enrollee_number' => $snapshot->enrollee_number,
            'legacy_id' => $snapshot->legacy_id,
            'full_name' => $snapshot->full_name,
            'nin' => $snapshot->nin,
            'phone' => $snapshot->phone,
            'gender' => $snapshot->gender,
            'date_of_birth' => $snapshot->date_of_birth?->toDateString(),
            'facility_name' => $snapshot->facility_name,
            'facility_code' => $snapshot->facility_code,
            'funding_type_name' => $snapshot->funding_type_name,
            'lga_name' => $snapshot->lga_name,
            'ward_name' => $snapshot->ward_name,
            'coverage_start_date' => $snapshot->coverage_start_date?->toDateString(),
            'coverage_end_date' => $snapshot->coverage_end_date?->toDateString(),
            'capitation_start_date' => $snapshot->capitation_start_date?->toDateString(),
            'duplicate_nin_policy' => $snapshot->duplicate_nin_policy,
            'has_duplicate_nin' => (bool) $snapshot->has_duplicate_nin,
            'snapshot_status' => $snapshot->snapshot_status,
            'snapshot_status_label' => match ((int) $snapshot->snapshot_status) {
                Enrollee::STATUS_PENDING => 'Pending Approval',
                Enrollee::STATUS_ACTIVE => 'Approved',
                Enrollee::STATUS_REJECTED => 'Rejected',
                Enrollee::STATUS_SUSPENDED => 'Suspended',
                Enrollee::STATUS_EXPIRED => 'Inactive',
                default => 'Unknown',
            },
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
        ];
    }
}
