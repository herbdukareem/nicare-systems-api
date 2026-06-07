<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClaimsDashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/claims
     */
    public function index(): JsonResponse
    {
        try {
            $userId    = auth()->id();
            $today     = now()->toDateString();
            $monthStart= now()->startOfMonth();
            $monthEnd  = now()->endOfMonth();

            // ---- Queue ----
            $awaitingReview    = Claim::where('status', 'SUBMITTED')->count();
            $currentlyReviewing= Claim::where('status', 'REVIEWING')->count();
            $myQueue           = Claim::where('status', 'REVIEWING')
                ->where('reviewed_by', $userId)
                ->count();

            // ---- Today ----
            $approvedToday = Claim::where('status', 'APPROVED')
                ->whereDate('approved_at', $today)
                ->where('approved_by', $userId)
                ->count();

            $rejectedToday = Claim::where('status', 'REJECTED')
                ->whereDate('rejected_at', $today)
                ->where('rejected_by', $userId)
                ->count();

            // Average review time in minutes (submitted_at → approved_at or rejected_at)
            $avgReviewMins = DB::table('claims')
                ->whereDate('updated_at', $today)
                ->whereIn('status', ['APPROVED', 'REJECTED'])
                ->whereNotNull('submitted_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, submitted_at, updated_at)) as avg_mins')
                ->value('avg_mins');

            // ---- This month ----
            $approvedMonth = Claim::where('status', 'APPROVED')
                ->whereBetween('approved_at', [$monthStart, $monthEnd])
                ->count();

            $rejectedMonth = Claim::where('status', 'REJECTED')
                ->whereBetween('rejected_at', [$monthStart, $monthEnd])
                ->count();

            $resubmittedMonth = Claim::whereBetween('submitted_at', [$monthStart, $monthEnd])
                ->whereNotNull('submitted_at')
                ->whereColumn('submitted_at', '>', 'created_at')
                ->count();

            $totalClaims = Claim::count();
            $totalReferrals = Referral::count();

            $totalAdjudicated = $approvedMonth + $rejectedMonth;
            $approvalRate     = $totalAdjudicated > 0
                ? round(($approvedMonth / $totalAdjudicated) * 100, 1)
                : 0;

            // ---- By facility (top 10) ----
            $byFacility = DB::table('claims')
                ->join('facilities', 'claims.facility_id', '=', 'facilities.id')
                ->selectRaw('
                    facilities.name as facility_name,
                    COUNT(*) as total,
                    SUM(CASE WHEN claims.status = "SUBMITTED" THEN 1 ELSE 0 END) as submitted,
                    SUM(CASE WHEN claims.status = "APPROVED"  THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN claims.status = "REJECTED"  THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN claims.status IN ("SUBMITTED","REVIEWING") THEN 1 ELSE 0 END) as pending
                ')
                ->groupBy('facilities.id', 'facilities.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            // ---- Turnaround ----
            $avgHours = DB::table('claims')
                ->whereIn('status', ['APPROVED', 'REJECTED'])
                ->whereNotNull('submitted_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, updated_at)) as avg_hours')
                ->value('avg_hours');

            $slowestClaim = Claim::whereIn('status', ['APPROVED', 'REJECTED'])
                ->whereNotNull('submitted_at')
                ->selectRaw('*, TIMESTAMPDIFF(HOUR, submitted_at, updated_at) as hours_taken')
                ->orderByRaw('TIMESTAMPDIFF(HOUR, submitted_at, updated_at) DESC')
                ->first();

            $claimsOver7Days = Claim::whereIn('status', ['SUBMITTED', 'REVIEWING'])
                ->where('submitted_at', '<=', now()->subDays(7))
                ->count();

            // ---- Recent adjudications by me (last 20) ----
            $recentAdjudications = Claim::where(function ($q) use ($userId) {
                    $q->where('approved_by', $userId)
                      ->orWhere('rejected_by', $userId);
                })
                ->orderByDesc('updated_at')
                ->limit(20)
                ->with(['facility', 'enrollee'])
                ->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'overview' => [
                        'total_claims' => $totalClaims,
                        'awaiting_review' => $awaitingReview,
                        'approved_month' => $approvedMonth,
                        'total_referrals' => $totalReferrals,
                    ],
                    'queue' => [
                        'awaiting_review'    => $awaitingReview,
                        'currently_reviewing'=> $currentlyReviewing,
                        'my_queue'           => $myQueue,
                    ],
                    'today' => [
                        'approved'               => $approvedToday,
                        'rejected'               => $rejectedToday,
                        'avg_review_time_minutes'=> (int) round((float) ($avgReviewMins ?? 0)),
                    ],
                    'this_month' => [
                        'approved'            => $approvedMonth,
                        'rejected'            => $rejectedMonth,
                        'resubmitted'         => $resubmittedMonth,
                        'approval_rate_percent'=> $approvalRate,
                    ],
                    'by_facility'         => $byFacility,
                    'turnaround'          => [
                        'avg_hours_submitted_to_reviewed' => (float) round((float) ($avgHours ?? 0), 1),
                        'slowest_claim'                   => $slowestClaim,
                        'claims_over_7_days'              => $claimsOver7Days,
                    ],
                    'recent_adjudications'=> $recentAdjudications,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch claims dashboard data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
