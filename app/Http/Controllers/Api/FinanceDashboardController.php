<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\Claim;
use App\Models\ClaimPaymentBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/finance
     */
    public function index(): JsonResponse
    {
        try {
            $now   = now();
            $month = $now->month;
            $year  = $now->year;

            // ---- Pending batches ----
            $pendingBatches = DB::table('claim_payment_batches')
                ->whereIn('status', ['pending', 'created'])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_value')
                ->first();

            // ---- Outstanding liability ----
            // Approved claims not yet in a paid batch
            $approvedUnpaid = Claim::where('status', 'APPROVED')
                ->whereNull('payment_batch_id')
                ->selectRaw('COALESCE(SUM(total_amount_claimed), 0) as total')
                ->value('total');

            // Capitation due: computed but not finalised in current month
            $capitationDue = CapitationDetail::whereHas('capitation', function ($q) use ($month, $year) {
                $q->where('capitation_month', $month)
                  ->where('year', $year)
                  ->where('status', false); // not finalised
            })->sum('total_amount');

            $outstandingTotal = (float) $approvedUnpaid + (float) $capitationDue;

            // ---- Processed this period (current calendar month) ----
            $processedBatches = DB::table('claim_payment_batches')
                ->where('status', 'paid')
                ->whereYear('updated_at', $year)
                ->whereMonth('updated_at', $month)
                ->selectRaw('COUNT(*) as batches_processed, COALESCE(SUM(total_amount), 0) as total_value_processed')
                ->first();

            $processedClaimsCount = Claim::where('status', 'APPROVED')
                ->whereYear('approved_at', $year)
                ->whereMonth('approved_at', $month)
                ->count();

            // ---- Capitation summary ----
            $currentPeriodLabel = $now->format('F Y');

            $computedCapitations = Capitation::where('year', $year)
                ->where('capitation_month', $month)
                ->count();

            $paidCapitations = Capitation::where('year', $year)
                ->where('capitation_month', $month)
                ->where('status', true)
                ->count();

            // Facilities with zero eligible enrollees (detail rows with 0 total_enrollees)
            $facilitiesZero = CapitationDetail::where('total_enrollees', 0)
                ->whereHas('capitation', fn ($q) => $q->where('year', $year)->where('capitation_month', $month))
                ->distinct('facility_id')
                ->count('facility_id');

            // ---- Recent batches (last 10) ----
            $recentBatches = DB::table('claim_payment_batches')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            // ---- Payment status breakdown ----
            $paymentBreakdown = DB::table('claim_payment_batches')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            return response()->json([
                'success' => true,
                'data'    => [
                    'pending_batches' => [
                        'count'       => (int) ($pendingBatches->count ?? 0),
                        'total_value' => number_format((float) ($pendingBatches->total_value ?? 0), 2, '.', ''),
                    ],
                    'outstanding_liability' => [
                        'approved_claims_unpaid' => number_format((float) $approvedUnpaid, 2, '.', ''),
                        'capitation_due'         => number_format((float) $capitationDue, 2, '.', ''),
                        'total'                  => number_format($outstandingTotal, 2, '.', ''),
                    ],
                    'processed_this_period' => [
                        'batches_processed'   => (int) ($processedBatches->batches_processed ?? 0),
                        'total_value_processed'=> number_format((float) ($processedBatches->total_value_processed ?? 0), 2, '.', ''),
                        'claims_count'        => $processedClaimsCount,
                    ],
                    'capitation_summary' => [
                        'current_period_label'        => $currentPeriodLabel,
                        'computed'                    => $computedCapitations,
                        'paid'                        => $paidCapitations,
                        'outstanding'                 => $computedCapitations - $paidCapitations,
                        'facilities_with_zero_enrollees' => $facilitiesZero,
                    ],
                    'recent_batches'           => $recentBatches,
                    'payment_status_breakdown' => [
                        'pending'    => (int) ($paymentBreakdown['pending'] ?? $paymentBreakdown['created'] ?? 0),
                        'processing' => (int) ($paymentBreakdown['processing'] ?? 0),
                        'paid'       => (int) ($paymentBreakdown['paid'] ?? 0),
                        'failed'     => (int) ($paymentBreakdown['failed'] ?? 0),
                    ],
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch finance dashboard data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
