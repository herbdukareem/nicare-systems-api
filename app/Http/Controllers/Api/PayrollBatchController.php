<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollBatch;
use App\Models\PayrollBatchEnrollee;
use App\Services\PremiumAuditService;
use App\Services\PremiumCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayrollBatchController extends Controller
{
    public function index(Request $request)
    {
        return PayrollBatch::with('enrollees')->latest()->paginate($request->get('per_page', 15));
    }

    public function store(Request $request, PremiumAuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'employer_name' => ['required', 'string', 'max:255'],
            'benefactor_id' => ['nullable', 'exists:benefactors,id'],
            'funding_type_id' => ['nullable', 'exists:funding_types,id'],
            'insurance_programme_id' => ['required', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'exists:enrollee_categories,id'],
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'rows' => ['nullable', 'array'],
            'file' => ['nullable', 'file', 'mimes:csv,txt'],
        ]);
        $rows = $data['rows'] ?? $this->rowsFromCsv($request);
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Payroll batch requires uploaded CSV rows or a rows array.'], 422);
        }

        $batch = PayrollBatch::create(collect($data)->except(['rows', 'file'])->all() + [
            'batch_code' => 'PAY-' . Str::upper(Str::random(10)),
            'uploaded_by' => auth()->id(),
        ]);

        foreach ($rows as $row) {
            PayrollBatchEnrollee::create($row + ['payroll_batch_id' => $batch->id, 'raw_payload' => $row]);
        }

        $audit->record($batch, 'payroll_batch_uploaded', "Payroll batch {$batch->batch_code} uploaded.", [], $batch->toArray());

        return response()->json(['success' => true, 'data' => $batch->load('enrollees')], 201);
    }

    public function approve(PayrollBatch $payrollBatch, PremiumCoverageService $service): JsonResponse
    {
        $count = $service->activatePayrollBatch($payrollBatch);

        return response()->json(['success' => true, 'covered_count' => $count, 'data' => $payrollBatch->fresh('enrollees')]);
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
