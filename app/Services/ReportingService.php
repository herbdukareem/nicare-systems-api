<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Claim;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\MobileSyncRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ReportingService (Phase 2 — 13 report types)
 * All methods return a standardised array:
 *   ['title', 'generated_at', 'generated_by', 'filters', 'data']
 */
class ReportingService
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function meta(string $title, array $filters): array
    {
        return [
            'title'        => $title,
            'generated_at' => now()->toDateTimeString(),
            'generated_by' => auth()->user()?->name ?? 'System',
            'filters'      => $filters,
        ];
    }

    // -------------------------------------------------------------------------
    // 1. Enrollment Summary
    // -------------------------------------------------------------------------

    public function enrollmentSummary(array $filters): array
    {
        $query = Enrollee::query()
            ->selectRaw('
                lga_id, ward_id, sex, enrollee_type_id, status,
                COUNT(*) as count
            ')
            ->groupBy('lga_id', 'ward_id', 'sex', 'enrollee_type_id', 'status');

        if (!empty($filters['lga_id'])) {
            $query->where('lga_id', $filters['lga_id']);
        }
        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return array_merge($this->meta('Enrollment Summary', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 2. Mobile Enrollment Activity
    // -------------------------------------------------------------------------

    public function mobileEnrollmentActivity(array $filters): array
    {
        $query = MobileSyncRecord::query()
            ->selectRaw('
                officer_user_id,
                DATE(created_at) as activity_date,
                status,
                COUNT(*) as count
            ')
            ->groupBy('officer_user_id', 'activity_date', 'status')
            ->orderBy('activity_date');

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return array_merge($this->meta('Mobile Enrollment Activity', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 3. Offline Sync Summary
    // -------------------------------------------------------------------------

    public function offlineSyncSummary(array $filters): array
    {
        $base = MobileSyncRecord::query();

        if (!empty($filters['from_date'])) {
            $base->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $base->where('created_at', '<=', $filters['to_date']);
        }

        $counts = $base->clone()->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

        $avgProcessing = DB::table('mobile_sync_records')
            ->whereNotNull('synced_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, synced_at)) as avg_seconds')
            ->value('avg_seconds');

        $data = [
            'total_pushed'     => $counts->sum(),
            'synced'           => (int) ($counts['synced'] ?? 0),
            'failed'           => (int) ($counts['failed'] ?? 0),
            'duplicate'        => (int) ($counts['duplicate'] ?? 0),
            'pending'          => (int) ($counts['pending'] ?? 0),
            'avg_processing_seconds' => (float) round((float)($avgProcessing ?? 0), 2),
        ];

        return array_merge($this->meta('Offline Sync Summary', $filters), ['data' => $data]);
    }

    // -------------------------------------------------------------------------
    // 4. Facility Utilization
    // -------------------------------------------------------------------------

    public function facilityUtilization(array $filters): array
    {
        $query = DB::table('facilities')
            ->selectRaw('
                facilities.id,
                facilities.name,
                COUNT(DISTINCT enrollees.id)    as enrollee_count,
                COUNT(DISTINCT referrals.id)    as referral_count,
                COUNT(DISTINCT admissions.id)   as admission_count,
                COUNT(DISTINCT claims.id)       as claim_count,
                COALESCE(SUM(claims.total_amount_claimed), 0) as total_claimed,
                COALESCE(SUM(CASE WHEN claims.status = "APPROVED" THEN claims.total_amount_claimed ELSE 0 END), 0) as total_approved
            ')
            ->leftJoin('enrollees',  'enrollees.facility_id',  '=', 'facilities.id')
            ->leftJoin('referrals',  'referrals.referring_facility_id', '=', 'facilities.id')
            ->leftJoin('admissions', 'admissions.facility_id', '=', 'facilities.id')
            ->leftJoin('claims',     'claims.facility_id',     '=', 'facilities.id')
            ->groupBy('facilities.id', 'facilities.name');

        if (!empty($filters['facility_id'])) {
            $query->where('facilities.id', $filters['facility_id']);
        }

        return array_merge($this->meta('Facility Utilization', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 5. Referral Pre-Auth
    // -------------------------------------------------------------------------

    public function referralPreauth(array $filters): array
    {
        $query = DB::table('referrals');

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['facility_id'])) {
            $query->where('referring_facility_id', $filters['facility_id']);
        }

        $statusCounts = $query->clone()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $avgDaysToApproval = $query->clone()
            ->where('status', 'APPROVED')
            ->whereNotNull('approval_date')
            ->selectRaw('AVG(DATEDIFF(approval_date, created_at)) as avg_days')
            ->value('avg_days');

        $total    = $statusCounts->sum();
        $approved = (int) ($statusCounts['APPROVED'] ?? 0);

        $data = [
            'status_breakdown'       => $statusCounts,
            'avg_days_to_approval'   => (float) round((float)($avgDaysToApproval ?? 0), 1),
            'approval_rate_percent'  => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
        ];

        return array_merge($this->meta('Referral Pre-Auth Report', $filters), ['data' => $data]);
    }

    // -------------------------------------------------------------------------
    // 6. Admission Report
    // -------------------------------------------------------------------------

    public function admission(array $filters): array
    {
        $query = DB::table('admissions')
            ->join('facilities', 'admissions.facility_id', '=', 'facilities.id')
            ->selectRaw('
                facilities.name as facility,
                COUNT(*) as total_admissions,
                SUM(CASE WHEN admissions.status = "discharged" THEN 1 ELSE 0 END) as discharged,
                AVG(admissions.ward_days) as avg_ward_days
            ')
            ->groupBy('facilities.id', 'facilities.name');

        if (!empty($filters['from_date'])) {
            $query->where('admissions.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('admissions.created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['facility_id'])) {
            $query->where('admissions.facility_id', $filters['facility_id']);
        }

        return array_merge($this->meta('Admission Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 7. Capitation Report
    // -------------------------------------------------------------------------

    public function capitation(array $filters): array
    {
        $query = DB::table('capitation_details')
            ->join('facilities', 'capitation_details.facility_id', '=', 'facilities.id')
            ->join('capitations', 'capitation_details.capitation_id', '=', 'capitations.id')
            ->selectRaw('
                facilities.name as facility,
                capitations.name as period,
                capitation_details.total_enrollees as enrollee_count,
                capitation_details.capitation_rate,
                capitation_details.total_amount as total_computed,
                COALESCE(SUM(capitation_payments.amount), 0) as total_paid,
                (capitation_details.total_amount - COALESCE(SUM(capitation_payments.amount), 0)) as outstanding
            ')
            ->leftJoin('capitation_payments', function ($j) {
                $j->on('capitation_payments.capitation_id', '=', 'capitation_details.capitation_id')
                  ->on('capitation_payments.facility_id', '=', 'capitation_details.facility_id');
            })
            ->groupBy('facilities.id', 'facilities.name', 'capitations.id', 'capitations.name',
                      'capitation_details.total_enrollees', 'capitation_details.capitation_rate',
                      'capitation_details.total_amount');

        if (!empty($filters['facility_id'])) {
            $query->where('capitation_details.facility_id', $filters['facility_id']);
        }

        return array_merge($this->meta('Capitation Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 8. Financial Liability
    // -------------------------------------------------------------------------

    public function financialLiability(array $filters): array
    {
        $query = DB::table('claims')
            ->join('facilities', 'claims.facility_id', '=', 'facilities.id')
            ->where('claims.status', 'APPROVED')
            ->whereNull('claims.payment_batch_id')
            ->selectRaw('
                facilities.name as facility,
                COUNT(*) as claim_count,
                SUM(claims.total_amount_claimed) as total_outstanding,
                SUM(CASE WHEN DATEDIFF(NOW(), claims.approved_at) BETWEEN 0  AND 30  THEN claims.total_amount_claimed ELSE 0 END) as bucket_0_30,
                SUM(CASE WHEN DATEDIFF(NOW(), claims.approved_at) BETWEEN 31 AND 60  THEN claims.total_amount_claimed ELSE 0 END) as bucket_31_60,
                SUM(CASE WHEN DATEDIFF(NOW(), claims.approved_at) BETWEEN 61 AND 90  THEN claims.total_amount_claimed ELSE 0 END) as bucket_61_90,
                SUM(CASE WHEN DATEDIFF(NOW(), claims.approved_at) > 90              THEN claims.total_amount_claimed ELSE 0 END) as bucket_over_90
            ')
            ->groupBy('facilities.id', 'facilities.name');

        if (!empty($filters['facility_id'])) {
            $query->where('claims.facility_id', $filters['facility_id']);
        }

        return array_merge($this->meta('Financial Liability Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 9. Payment Report
    // -------------------------------------------------------------------------

    public function payment(array $filters): array
    {
        $query = DB::table('claim_payment_batches')
            ->leftJoin('facilities', 'claim_payment_batches.facility_id', '=', 'facilities.id')
            ->selectRaw('
                facilities.name as facility,
                claim_payment_batches.id as batch_id,
                claim_payment_batches.status,
                claim_payment_batches.total_amount,
                claim_payment_batches.created_at
            ');

        if (!empty($filters['from_date'])) {
            $query->where('claim_payment_batches.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('claim_payment_batches.created_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['facility_id'])) {
            $query->where('claim_payment_batches.facility_id', $filters['facility_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('claim_payment_batches.status', $filters['status']);
        }

        $batches = $query->orderByDesc('claim_payment_batches.created_at')->get();

        $summary = [
            'total_batches' => $batches->count(),
            'total_amount'  => $batches->sum('total_amount'),
            'paid'          => $batches->where('status', 'paid')->sum('total_amount'),
            'pending'       => $batches->whereIn('status', ['pending', 'created'])->sum('total_amount'),
        ];

        return array_merge($this->meta('Payment Report', $filters), ['data' => ['summary' => $summary, 'batches' => $batches->toArray()]]);
    }

    // -------------------------------------------------------------------------
    // 10. Rejected Claims
    // -------------------------------------------------------------------------

    public function rejectedClaims(array $filters): array
    {
        $query = DB::table('claims')
            ->join('facilities', 'claims.facility_id', '=', 'facilities.id')
            ->where('claims.status', 'REJECTED')
            ->selectRaw('
                facilities.name as facility,
                claims.rejection_reason,
                COUNT(*) as count
            ')
            ->groupBy('facilities.id', 'facilities.name', 'claims.rejection_reason')
            ->orderByDesc('count');

        if (!empty($filters['from_date'])) {
            $query->where('claims.rejected_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('claims.rejected_at', '<=', $filters['to_date']);
        }
        if (!empty($filters['facility_id'])) {
            $query->where('claims.facility_id', $filters['facility_id']);
        }

        return array_merge($this->meta('Rejected Claims Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 11. Audit Activity
    // -------------------------------------------------------------------------

    public function auditActivity(array $filters): array
    {
        $query = DB::table('audit_trails')
            ->selectRaw('action, user_id, auditable_type, COUNT(*) as count')
            ->groupBy('action', 'user_id', 'auditable_type')
            ->orderByDesc('count');

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return array_merge($this->meta('Audit Activity Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 12. User Activity
    // -------------------------------------------------------------------------

    public function userActivity(array $filters): array
    {
        $query = DB::table('audit_trails')
            ->join('users', 'audit_trails.user_id', '=', 'users.id')
            ->selectRaw('users.name as user, action, COUNT(*) as count')
            ->groupBy('audit_trails.user_id', 'users.name', 'action')
            ->orderByDesc('count');

        if (!empty($filters['from_date'])) {
            $query->where('audit_trails.created_at', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where('audit_trails.created_at', '<=', $filters['to_date']);
        }

        return array_merge($this->meta('User Activity Report', $filters), ['data' => $query->get()->toArray()]);
    }

    // -------------------------------------------------------------------------
    // 13. Executive Summary
    // -------------------------------------------------------------------------

    public function executiveSummary(array $filters): array
    {
        $totalEnrollees  = Enrollee::count();
        $totalFacilities = Facility::count();

        $claimsByStatus = DB::table('claims')
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount_claimed), 0) as total_amount')
            ->groupBy('status')
            ->get();

        $totalApproved = $claimsByStatus->where('status', 'APPROVED')->sum('total_amount');

        $totalPaid = DB::table('claim_payment_batches')
            ->where('status', 'paid')
            ->sum('total_amount');

        $outstandingLiability = Claim::where('status', 'APPROVED')
            ->whereNull('payment_batch_id')
            ->sum('total_amount_claimed');

        $referrals  = DB::table('referrals')->selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');
        $totalRef   = $referrals->sum();
        $approvedRef= (int)($referrals['APPROVED'] ?? 0);
        $approvalRate = $totalRef > 0 ? round(($approvedRef / $totalRef) * 100, 1) : 0;

        $kpis = [
            ['label' => 'Total Enrollees',   'value' => number_format($totalEnrollees)],
            ['label' => 'Total Facilities',  'value' => number_format($totalFacilities)],
            ['label' => 'Total Approved (NGN)', 'value' => number_format((float)$totalApproved, 2)],
            ['label' => 'Total Paid (NGN)',   'value' => number_format((float)$totalPaid, 2)],
            ['label' => 'Outstanding (NGN)',  'value' => number_format((float)$outstandingLiability, 2)],
            ['label' => 'Referral Approval Rate', 'value' => $approvalRate . '%'],
        ];

        $data = [
            'kpis'             => $kpis,
            'claims_by_status' => $claimsByStatus->toArray(),
        ];

        return array_merge($this->meta('Executive Summary', $filters), ['data' => $data]);
    }
}
