<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Claim;
use Carbon\Carbon;

/**
 * ReportingService
 * 
 * Generates reports for claims, payments, and compliance
 */
class ReportingService
{
    /**
     * Generate claims report
     * 
     * @param array $filters - date_from, date_to, facility_id, status
     * @return array
     */
    public function getClaimsReport(array $filters = []): array
    {
        $query = Claim::query();

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $claims = $query->get();

        return [
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
            'summary' => [
                'total_claims' => $claims->count(),
                'draft_claims' => $claims->where('status', 'DRAFT')->count(),
                'submitted_claims' => $claims->where('status', 'SUBMITTED')->count(),
                'approved_claims' => $claims->where('status', 'APPROVED')->count(),
                'rejected_claims' => $claims->where('status', 'REJECTED')->count(),
            ],
            'financial' => [
                'total_claimed' => $claims->sum('total_amount_claimed'),
                'bundle_total' => $claims->sum('bundle_amount'),
                'ffs_total' => $claims->sum('ffs_amount'),
                'approved_amount' => $claims->where('status', 'APPROVED')->sum('total_amount_claimed'),
            ],
            'claims' => $claims->map(fn($c) => [
                'id' => $c->id,
                'status' => $c->status,
                'total_claimed' => $c->total_amount_claimed,
                'created_at' => $c->created_at,
            ]),
        ];
    }

    /**
     * Generate payment report
     * 
     * @param array $filters - date_from, date_to, facility_id, status
     * @return array
     */
    public function getPaymentReport(array $filters = []): array
    {
        $query = Claim::where('status', 'APPROVED');

        if (isset($filters['date_from'])) {
            $query->where('approved_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('approved_at', '<=', $filters['date_to']);
        }

        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (isset($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }

        $claims = $query->get();

        return [
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
            'summary' => [
                'total_approved_claims' => $claims->count(),
                'processed_claims' => $claims->where('payment_status', 'PROCESSED')->count(),
                'pending_claims' => $claims->where('payment_status', '!=', 'PROCESSED')->count(),
            ],
            'financial' => [
                'total_approved_amount' => $claims->sum('total_amount_claimed'),
                'processed_amount' => $claims->where('payment_status', 'PROCESSED')->sum('total_amount_claimed'),
                'pending_amount' => $claims->where('payment_status', '!=', 'PROCESSED')->sum('total_amount_claimed'),
            ],
            'payments' => $claims->map(fn($c) => [
                'claim_id' => $c->id,
                'amount' => $c->total_amount_claimed,
                'status' => $c->payment_status ?? 'PENDING',
                'reference' => $c->payment_reference ?? null,
                'approved_at' => $c->approved_at,
            ]),
        ];
    }

    /**
     * Generate compliance report
     * 
     * @param array $filters - date_from, date_to, facility_id
     * @return array
     */
    public function getComplianceReport(array $filters = []): array
    {
        $query = Claim::query();

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        $claims = $query->get();

        $rejectedClaims = $claims->where('status', 'REJECTED');
        $bundleViolations = $claims->filter(function ($c) {
            return $c->lineItems()->where('tariff_type', 'BUNDLE')->count() > 1;
        });

        return [
            'period' => [
                'from' => $filters['date_from'] ?? null,
                'to' => $filters['date_to'] ?? null,
            ],
            'summary' => [
                'total_claims_reviewed' => $claims->count(),
                'rejected_claims' => $rejectedClaims->count(),
                'rejection_rate' => $claims->count() > 0 ? round(($rejectedClaims->count() / $claims->count()) * 100, 2) : 0,
                'policy_violations' => $bundleViolations->count(),
            ],
            'violations' => [
                'double_bundle_violations' => $bundleViolations->count(),
                'unauthorized_ffs_violations' => 0, // Would need alert tracking
                'missing_pa_violations' => 0, // Would need alert tracking
            ],
        ];
    }
}

