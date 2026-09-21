<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DuplicateNinVerificationBatch;
use App\Models\DuplicateNinVerificationItem;
use App\Services\DuplicateNinVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DuplicateNinVerificationController extends Controller
{
    public function __construct(private readonly DuplicateNinVerificationService $service)
    {
    }

    public function index(): JsonResponse
    {
        $batches = DuplicateNinVerificationBatch::query()
            ->with('creator:id,name')
            ->withCount([
                'items',
                'items as applied_items_count' => fn ($query) => $query->where('status', 'applied'),
                'items as needs_review_items_count' => fn ($query) => $query->where('status', 'needs_review'),
                'items as failed_items_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (DuplicateNinVerificationBatch $batch): array => $this->serializeBatch($batch));

        return response()->json([
            'success' => true,
            'data' => $batches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $batch = $this->service->createBatch((int) $validated['count'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Duplicate NIN verification batch prepared.',
            'data' => $this->serializeBatch($batch, true),
        ], 201);
    }

    public function show(DuplicateNinVerificationBatch $batch): JsonResponse
    {
        $batch->load([
            'creator:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.candidates' => fn ($query) => $query->orderByDesc('match_score')->orderBy('id'),
            'items.candidates.enrollee:id,image_url',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeBatch($batch, true),
        ]);
    }

    public function verifyItem(Request $request, DuplicateNinVerificationItem $item): JsonResponse
    {
        try {
            $item = $this->service->verifyItem($item, $request->user(), true);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify this duplicate NIN right now.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Duplicate NIN verified.',
            'data' => $this->serializeItem($item->fresh(['candidates.enrollee'])),
        ]);
    }

    public function verifyBatch(Request $request, DuplicateNinVerificationBatch $batch): JsonResponse
    {
        try {
            $batch = $this->service->verifyBatch($batch, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Duplicate NIN batch verification completed.',
            'data' => $this->serializeBatch($batch, true),
        ]);
    }

    public function decide(Request $request, DuplicateNinVerificationItem $item): JsonResponse
    {
        $validated = $request->validate([
            'selected_enrollee_id' => ['required', 'integer', 'exists:enrollees,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $item = $this->service->applyDecision(
                $item,
                (int) $validated['selected_enrollee_id'],
                $request->user(),
                'manual',
                $validated['note'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Duplicate NIN decision applied.',
            'data' => $this->serializeItem($item->fresh(['candidates.enrollee'])),
        ]);
    }

    private function serializeBatch(DuplicateNinVerificationBatch $batch, bool $includeItems = false): array
    {
        if ($includeItems) {
            $batch->loadMissing('items.candidates.enrollee:id,image_url');
        }

        $payload = [
            'id' => $batch->id,
            'reference' => $batch->reference,
            'requested_count' => $batch->requested_count,
            'unique_nin_count' => $batch->unique_nin_count,
            'total_candidate_count' => $batch->total_candidate_count,
            'status' => $batch->status,
            'created_by' => $batch->creator?->name,
            'created_at' => $batch->created_at?->toIso8601String(),
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'items_count' => $batch->items_count ?? $batch->items?->count(),
            'applied_items_count' => $batch->applied_items_count ?? null,
            'needs_review_items_count' => $batch->needs_review_items_count ?? null,
            'failed_items_count' => $batch->failed_items_count ?? null,
        ];

        if ($includeItems) {
            $payload['items'] = $batch->items
                ->map(fn (DuplicateNinVerificationItem $item): array => $this->serializeItem($item))
                ->values();
        }

        return $payload;
    }

    private function serializeItem(DuplicateNinVerificationItem $item): array
    {
        $item->loadMissing('candidates.enrollee:id,image_url');

        return [
            'id' => $item->id,
            'batch_id' => $item->batch_id,
            'nin' => $item->nin,
            'status' => $item->status,
            'provider_name' => $item->provider_name,
            'provider_data' => $item->provider_data ?: [],
            'comparison_summary' => $item->comparison_summary ?: [],
            'matched_enrollee_id' => $item->matched_enrollee_id,
            'decision_source' => $item->decision_source,
            'decision_note' => $item->decision_note,
            'verified_at' => $item->verified_at?->toIso8601String(),
            'applied_at' => $item->applied_at?->toIso8601String(),
            'failure_message' => $item->failure_message,
            'candidates' => $item->candidates
                ->map(fn ($candidate): array => [
                    'id' => $candidate->id,
                    'enrollee_id' => $candidate->enrollee_id,
                    'enrollee_code' => $candidate->enrollee_code,
                    'full_name' => $candidate->full_name,
                    'first_name' => $candidate->first_name,
                    'middle_name' => $candidate->middle_name,
                    'last_name' => $candidate->last_name,
                    'date_of_birth' => $candidate->date_of_birth?->toDateString(),
                    'gender' => $candidate->gender,
                    'phone' => $candidate->phone,
                    'photo_url' => $candidate->enrollee?->image_url,
                    'original_nin' => $candidate->original_nin,
                    'status_before' => $candidate->status_before,
                    'match_score' => $candidate->match_score,
                    'match_result' => $candidate->match_result ?: [],
                    'keeps_nin' => (bool) $candidate->keeps_nin,
                    'nin_cleared' => (bool) $candidate->nin_cleared,
                ])
                ->values(),
        ];
    }
}
