<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClaimsAutomation\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    private ReportingService $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Get claims report
     * GET /api/reports/claims
     */
    public function claimsReport(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'facility_id', 'status']);

        $report = $this->reportingService->getClaimsReport($filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get payment report
     * GET /api/reports/payments
     */
    public function paymentReport(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'facility_id', 'status']);

        $report = $this->reportingService->getPaymentReport($filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get compliance report
     * GET /api/reports/compliance
     */
    public function complianceReport(Request $request): JsonResponse
    {
        $filters = $request->only(['date_from', 'date_to', 'facility_id']);

        $report = $this->reportingService->getComplianceReport($filters);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}

