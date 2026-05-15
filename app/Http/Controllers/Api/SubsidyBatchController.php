<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubsidyBatch;
use App\Models\SubsidyBatchEnrollee;
use App\Services\PremiumAuditService;
use App\Services\PremiumCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubsidyBatchController extends Controller
{
    public function index(Request $request)
    {
        return SubsidyBatch::with('enrollees')->latest()->paginate($request->get('per_page', 15));
    }

    public function store(Request $request, PremiumAuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'funding_source' => ['required', 'string', 'max:255'],
            'benefactor_id' => ['nullable', 'exists:benefactors,id'],
            'funding_type_id' => ['nullable', 'exists:funding_types,id'],
            'insurance_programme_id' => ['required', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'exists:enrollee_categories,id'],
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'coverage_start_date' => ['required', 'date'],
            'coverage_end_date' => ['required', 'date', 'after_or_equal:coverage_start_date'],
            'rows' => ['nullable', 'array'],
            'file' => ['nullable', 'file', 'mimes:csv,txt'],
        ]);
        $rows = $data['rows'] ?? $this->rowsFromCsv($request);
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Subsidy batch requires uploaded CSV rows or a rows array.'], 422);
        }

        $batch = SubsidyBatch::create(collect($data)->except(['rows', 'file'])->all() + [
            'batch_code' => 'SUB-' . Str::upper(Str::random(10)),
            'uploaded_by' => auth()->id(),
        ]);

        foreach ($rows as $row) {
            SubsidyBatchEnrollee::create($row + ['subsidy_batch_id' => $batch->id, 'raw_payload' => $row]);
        }

        $audit->record($batch, 'subsidy_batch_uploaded', "Subsidy batch {$batch->batch_code} uploaded.", [], $batch->toArray());

        return response()->json(['success' => true, 'data' => $batch->load('enrollees')], 201);
    }

    public function approve(SubsidyBatch $subsidyBatch, PremiumCoverageService $service): JsonResponse
    {
        $count = $service->activateSubsidyBatch($subsidyBatch);

        return response()->json(['success' => true, 'covered_count' => $count, 'data' => $subsidyBatch->fresh('enrollees')]);
    }

    private function rowsFromCsv(Request $request): array
    {
        if (!$request->hasFile('file')) {
            return [];
        }

        $handle = fopen($request->file('file')->getRealPath(), 'rb');
        $headers = fgetcsv($handle) ?: [];
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_filter(array_combine($headers, $line) ?: [], fn ($value) => $value !== null && $value !== '');
        }
        fclose($handle);

        return $rows;
    }
}
