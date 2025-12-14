<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimPaymentBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentBatchController extends Controller
{
    /**
     * Get list of payment batches
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ClaimPaymentBatch::with(['facility', 'createdBy', 'processedBy', 'paidBy']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('facility_id')) {
                $query->where('facility_id', $request->facility_id);
            }

            if ($request->filled('batch_month')) {
                $query->where('batch_month', $request->batch_month);
            }

            $query->orderBy('created_at', 'desc');
            $perPage = $request->get('per_page', 15);
            $batches = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $batches,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment batches: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a payment batch from approved claims
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'batch_month' => 'required|date_format:Y-m',
                'facility_id' => 'nullable|integer|exists:facilities,id',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            // Get approved claims for the month that are not yet in a batch
            $query = Claim::where('status', 'APPROVED')
                ->whereNull('payment_batch_id')
                ->whereYear('approved_at', substr($validated['batch_month'], 0, 4))
                ->whereMonth('approved_at', substr($validated['batch_month'], 5, 2));

            if (!empty($validated['facility_id'])) {
                $query->where('facility_id', $validated['facility_id']);
            }

            $claims = $query->get();

            if ($claims->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approved claims found for this period',
                ], 400);
            }

            // Calculate totals
            $totalBundle = $claims->sum('bundle_amount');
            $totalFFS = $claims->sum('ffs_amount');
            $totalAmount = $claims->sum(fn($c) => $c->approved_amount ?? $c->total_amount_claimed);

            // Create batch
            $batch = ClaimPaymentBatch::create([
                'batch_number' => ClaimPaymentBatch::generateBatchNumber(),
                'batch_month' => $validated['batch_month'],
                'facility_id' => $validated['facility_id'] ?? null,
                'total_claims' => $claims->count(),
                'total_bundle_amount' => $totalBundle,
                'total_ffs_amount' => $totalFFS,
                'total_amount' => $totalAmount,
                'status' => ClaimPaymentBatch::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Link claims to batch
            Claim::whereIn('id', $claims->pluck('id'))
                ->update(['payment_batch_id' => $batch->id]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment batch created successfully',
                'data' => $batch->load(['facility', 'claims']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment batch: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment batch details
     */
    public function show(ClaimPaymentBatch $batch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $batch->load([
                'facility',
                'claims.enrollee',
                'claims.referral',
                'createdBy',
                'processedBy',
                'paidBy',
            ]),
        ]);
    }

    /**
     * Process payment batch (mark as processing)
     */
    public function process(Request $request, ClaimPaymentBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_reference' => 'nullable|string',
                'payment_method' => 'nullable|string|in:BANK_TRANSFER,CHEQUE',
                'bank_details' => 'nullable|string',
                'payment_date' => 'nullable|date',
            ]);

            if ($batch->status !== ClaimPaymentBatch::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending batches can be processed',
                ], 400);
            }

            $batch->update([
                'status' => ClaimPaymentBatch::STATUS_PROCESSING,
                'payment_reference' => $validated['payment_reference'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'bank_details' => $validated['bank_details'] ?? null,
                'payment_date' => $validated['payment_date'] ?? now(),
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment batch marked for processing',
                'data' => $batch->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark payment batch as paid
     */
    public function markPaid(Request $request, ClaimPaymentBatch $batch): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_reference' => 'required|string',
                'notes' => 'nullable|string',
            ]);

            if ($batch->status !== ClaimPaymentBatch::STATUS_PROCESSING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only processing batches can be marked as paid',
                ], 400);
            }

            DB::beginTransaction();

            $batch->update([
                'status' => ClaimPaymentBatch::STATUS_PAID,
                'payment_reference' => $validated['payment_reference'],
                'notes' => $validated['notes'] ?? $batch->notes,
                'paid_by' => auth()->id(),
                'paid_at' => now(),
            ]);

            // Update all claims in batch
            Claim::where('payment_batch_id', $batch->id)
                ->update([
                    'payment_status' => 'PAID',
                    'payment_reference' => $validated['payment_reference'],
                    'payment_processed_at' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment batch marked as paid',
                'data' => $batch->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark batch as paid: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download batch payment receipt PDF
     */
    public function downloadReceipt(ClaimPaymentBatch $batch)
    {
        try {
            $batch->load([
                'facility',
                'claims.enrollee',
                'claims.referral',
                'createdBy',
                'paidBy',
            ]);

            $data = [
                'batch' => $batch,
                'generated_at' => now()->format('d M Y, H:i:s'),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.batch-payment-receipt', $data);
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download("batch-receipt-{$batch->batch_number}.pdf");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get approved claims ready for batching
     */
    public function getApprovedClaims(Request $request): JsonResponse
    {
        try {
            $query = Claim::with(['enrollee', 'facility', 'referral'])
                ->where('status', 'APPROVED')
                ->whereNull('payment_batch_id');

            if ($request->filled('facility_id')) {
                $query->where('facility_id', $request->facility_id);
            }

            if ($request->filled('month')) {
                $query->whereYear('approved_at', substr($request->month, 0, 4))
                      ->whereMonth('approved_at', substr($request->month, 5, 2));
            }

            $claims = $query->orderBy('approved_at', 'desc')->get();

            // Group by facility for summary
            $summary = $claims->groupBy('facility_id')->map(function ($facilityClaims, $facilityId) {
                return [
                    'facility_id' => $facilityId,
                    'facility_name' => $facilityClaims->first()->facility?->name,
                    'total_claims' => $facilityClaims->count(),
                    'total_amount' => $facilityClaims->sum(fn($c) => $c->approved_amount ?? $c->total_amount_claimed),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'claims' => $claims,
                    'summary' => $summary,
                    'totals' => [
                        'total_claims' => $claims->count(),
                        'total_amount' => $claims->sum(fn($c) => $c->approved_amount ?? $c->total_amount_claimed),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get approved claims: ' . $e->getMessage(),
            ], 500);
        }
    }
}

