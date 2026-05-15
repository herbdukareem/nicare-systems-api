<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Claim;
use App\Models\Enrollee;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FacilityDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/facility
     * All queries are scoped to the authenticated user's facility_id.
     */
    public function index(): JsonResponse
    {
        try {
            $user       = auth()->user();
            $facilityId = $user->userable?->facility_id ?? $user->facility_id ?? null;

            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No facility associated with this user.',
                ], 403);
            }

            $now       = now();
            $monthStart= $now->copy()->startOfMonth();
            $monthEnd  = $now->copy()->endOfMonth();
            $ninetyDaysAgo = $now->copy()->subDays(90);

            // ---- Enrollees ----
            $enrolleeTotal  = Enrollee::where('facility_id', $facilityId)->count();
            $enrolleeActive = Enrollee::where('facility_id', $facilityId)
                ->where('status', 1)
                ->whereDate('coverage_start_date', '<=', $now)
                ->where(function ($query) use ($now) {
                    $query->whereNull('coverage_end_date')
                        ->orWhereDate('coverage_end_date', '>=', $now);
                })
                ->count();

            // ---- Referrals ----
            $referralsPendingCreation = Referral::where('referring_facility_id', $facilityId)
                ->where('status', 'PENDING')
                ->count();

            $referralsAwaitingApproval = Referral::where('referring_facility_id', $facilityId)
                ->where('status', 'SUBMITTED')
                ->count();

            $referralsApprovedThisMonth = Referral::where('referring_facility_id', $facilityId)
                ->where('status', 'APPROVED')
                ->whereBetween('approval_date', [$monthStart, $monthEnd])
                ->count();

            $referralsDeniedThisMonth = Referral::where('referring_facility_id', $facilityId)
                ->where('status', 'REJECTED')
                ->whereBetween('updated_at', [$monthStart, $monthEnd])
                ->count();

            // ---- Admissions ----
            $currentlyAdmitted = Admission::where('facility_id', $facilityId)
                ->where('status', 'active')
                ->count();

            $dischargedThisMonth = Admission::where('facility_id', $facilityId)
                ->where('status', 'discharged')
                ->whereBetween('discharge_date', [$monthStart, $monthEnd])
                ->count();

            // ---- Claims ----
            $claimCounts = Claim::where('facility_id', $facilityId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            // ---- Payments ----
            $totalPaid = DB::table('claim_payment_batches')
                ->where('facility_id', $facilityId)
                ->where('status', 'paid')
                ->sum('total_amount');

            $lastPayment = DB::table('claim_payment_batches')
                ->where('facility_id', $facilityId)
                ->where('status', 'paid')
                ->orderByDesc('updated_at')
                ->first();

            $pendingPayment = Claim::where('facility_id', $facilityId)
                ->where('status', 'APPROVED')
                ->whereNull('payment_batch_id')
                ->sum('total_amount_claimed');

            // ---- Top rejection reasons (last 90 days, top 5) ----
            $topRejectionReasons = Claim::where('facility_id', $facilityId)
                ->where('status', 'REJECTED')
                ->where('rejected_at', '>=', $ninetyDaysAgo)
                ->whereNotNull('rejection_reason')
                ->selectRaw('rejection_reason as reason, COUNT(*) as count')
                ->groupBy('rejection_reason')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'enrollees' => [
                        'total_assigned' => $enrolleeTotal,
                        'active'         => $enrolleeActive,
                        'inactive'       => $enrolleeTotal - $enrolleeActive,
                    ],
                    'referrals' => [
                        'pending_creation'   => $referralsPendingCreation,
                        'awaiting_approval'  => $referralsAwaitingApproval,
                        'approved_this_month'=> $referralsApprovedThisMonth,
                        'denied_this_month'  => $referralsDeniedThisMonth,
                    ],
                    'admissions' => [
                        'currently_admitted'    => $currentlyAdmitted,
                        'discharged_this_month' => $dischargedThisMonth,
                    ],
                    'claims' => [
                        'draft'        => (int) ($claimCounts['DRAFT'] ?? 0),
                        'submitted'    => (int) ($claimCounts['SUBMITTED'] ?? 0),
                        'under_review' => (int) ($claimCounts['REVIEWING'] ?? 0),
                        'approved'     => (int) ($claimCounts['APPROVED'] ?? 0),
                        'rejected'     => (int) ($claimCounts['REJECTED'] ?? 0),
                    ],
                    'payments' => [
                        'total_paid_to_date' => number_format((float) $totalPaid, 2, '.', ''),
                        'last_payment'       => $lastPayment,
                        'pending_payment'    => number_format((float) $pendingPayment, 2, '.', ''),
                    ],
                    'top_rejection_reasons' => $topRejectionReasons,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch facility dashboard data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
