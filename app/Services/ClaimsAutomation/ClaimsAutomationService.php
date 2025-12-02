<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\Bundle;
use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use App\Models\ClaimSection;
use App\Models\ClaimTreatment;
use App\Models\PACode;
use Illuminate\Support\Collection;

class ClaimsAutomationService
{
    protected AdmissionService $admissionService;
    protected BundleClassificationService $bundleService;
    protected PAAutomationService $paService;
    protected ComplianceValidationService $complianceService;
    protected JustificationGeneratorService $justificationService;

    public function __construct(
        AdmissionService $admissionService,
        BundleClassificationService $bundleService,
        PAAutomationService $paService,
        ComplianceValidationService $complianceService,
        JustificationGeneratorService $justificationService
    ) {
        $this->admissionService = $admissionService;
        $this->bundleService = $bundleService;
        $this->paService = $paService;
        $this->complianceService = $complianceService;
        $this->justificationService = $justificationService;
    }

    /**
     * Process a claim through the automation pipeline
     */
    public function processClaim(Claim $claim): array
    {
        $results = [
            'claim_id' => $claim->id,
            'steps' => [],
        ];

        // Step 1: Classify all treatments as bundle or FFS
        $classifications = $this->bundleService->classifyAllTreatments($claim);
        $results['steps']['classification'] = [
            'status' => 'completed',
            'treatments_classified' => $classifications->count(),
        ];

        // Step 2: Check for missing PA codes and auto-generate if needed
        $missingPAs = $this->paService->detectMissingPAs($claim);
        $results['steps']['pa_check'] = [
            'status' => 'completed',
            'missing_pas' => $missingPAs->count(),
        ];

        // Step 3: Build claim sections (A, B, C)
        $sections = $this->buildClaimSections($claim);
        $results['steps']['sections'] = [
            'status' => 'completed',
            'sections_created' => $sections->count(),
        ];

        // Step 4: Generate justification text
        $justification = $this->justificationService->generateClaimJustification($claim);
        $results['steps']['justification'] = [
            'status' => 'completed',
            'text_length' => strlen($justification),
        ];

        // Step 5: Run compliance validation
        $validation = $this->complianceService->validateClaim($claim);
        $results['steps']['validation'] = [
            'status' => 'completed',
            'is_valid' => $validation['is_valid'],
            'alerts' => $validation['summary'],
        ];

        $results['is_ready_for_submission'] = $validation['is_valid'];
        $results['claim'] = $claim->fresh()->load(['sections', 'treatments', 'diagnoses']);

        return $results;
    }

    /**
     * Build structured claim sections (A, B, C)
     */
    public function buildClaimSections(Claim $claim): Collection
    {
        $sections = collect();

        // Delete existing sections
        $claim->sections()->delete();

        // Section A: Principal Bundle
        $sectionA = $this->buildSectionA($claim);
        if ($sectionA) {
            $sections->push($sectionA);
        }

        // Section B: FFS Top-Ups
        $sectionB = $this->buildSectionB($claim);
        if ($sectionB) {
            $sections->push($sectionB);
        }

        // Section C: Medical Justification
        $sectionC = $this->justificationService->generateSectionC($claim);
        $sections->push($sectionC);

        return $sections;
    }

    /**
     * Build Section A (Principal Bundle)
     */
    protected function buildSectionA(Claim $claim): ?ClaimSection
    {
        $principalTreatment = $claim->treatments()
            ->where('is_principal_bundle', true)
            ->first();

        if (!$principalTreatment) {
            return null;
        }

        $primaryDiagnosis = $claim->diagnoses()
            ->where('type', 'primary')
            ->first();

        $bundle = $claim->principalBundle;
        $paCode = $claim->paCode;

        return ClaimSection::create([
            'claim_id' => $claim->id,
            'section_type' => ClaimSection::SECTION_A,
            'section_title' => 'Principal Bundle',
            'bundle_id' => $bundle?->id,
            'principal_diagnosis_code' => $primaryDiagnosis?->icd_10_code,
            'principal_diagnosis_description' => $primaryDiagnosis?->diagnosis_description,
            'bundle_amount' => $principalTreatment->total_amount,
            'section_total' => $principalTreatment->total_amount,
            'pa_reference' => 'PA-1',
            'pa_code_id' => $paCode?->id,
            'status' => 'draft',
        ]);
    }

    /**
     * Build Section B (FFS Top-Ups)
     */
    protected function buildSectionB(Claim $claim): ?ClaimSection
    {
        $ffsTreatments = $claim->treatments()
            ->where('tariff_type', 'ffs')
            ->get();

        if ($ffsTreatments->isEmpty()) {
            return null;
        }

        // Calculate subtotals by category
        $wardDays = $ffsTreatments->where('ffs_reason', 'extended_stay')->sum('total_amount');
        $medications = $ffsTreatments->where('ffs_reason', 'additional_medication')->sum('total_amount');
        $diagnostics = $ffsTreatments->where('ffs_reason', 'extra_diagnostics')->sum('total_amount');
        $procedures = $ffsTreatments->whereIn('ffs_reason', ['complication', 'emergency_procedure'])->sum('total_amount');
        $specialists = $ffsTreatments->where('ffs_reason', 'specialist_review')->sum('total_amount');
        $consumables = $ffsTreatments->where('ffs_reason', 'consumables')->sum('total_amount');

        $total = $wardDays + $medications + $diagnostics + $procedures + $specialists + $consumables;

        return ClaimSection::create([
            'claim_id' => $claim->id,
            'section_type' => ClaimSection::SECTION_B,
            'section_title' => 'FFS Top-Ups',
            'ward_days_amount' => $wardDays,
            'medications_amount' => $medications,
            'diagnostics_amount' => $diagnostics,
            'procedures_amount' => $procedures,
            'specialist_reviews_amount' => $specialists,
            'consumables_amount' => $consumables,
            'section_total' => $total,
            'status' => 'draft',
        ]);
    }

    /**
     * Handle new diagnosis detection during admission
     */
    public function handleNewDiagnosis(Claim $claim, array $diagnosisData): array
    {
        $admission = $claim->admission;

        // Determine if this is a complication or secondary diagnosis
        $isComplication = $diagnosisData['is_complication'] ?? false;
        $diagnosisType = $isComplication ? 'complication' : 'secondary';

        // Create the diagnosis
        $diagnosis = ClaimDiagnosis::create([
            'claim_id' => $claim->id,
            'icd_10_code' => $diagnosisData['icd_10_code'],
            'icd_10_description' => $diagnosisData['description'],
            'type' => $diagnosisType,
        ]);

        // Auto-generate PA for the new diagnosis
        $paCode = null;
        if ($admission) {
            $paCode = $this->paService->autoGeneratePAForComplication($claim, $diagnosis);
        }

        // Update claim flags
        if ($isComplication) {
            $claim->update(['has_complications' => true]);
        }

        return [
            'diagnosis' => $diagnosis,
            'pa_code' => $paCode,
            'requires_ffs' => true,
            'message' => "New {$diagnosisType} detected. PA code generated: " . ($paCode?->utn ?? 'N/A'),
        ];
    }

    /**
     * Convert a bundle item to FFS (for double-bundle resolution)
     */
    public function convertBundleToFFS(ClaimTreatment $treatment, string $reason): ClaimTreatment
    {
        $treatment->update([
            'tariff_type' => 'ffs',
            'is_principal_bundle' => false,
            'is_ffs_topup' => true,
            'ffs_reason' => $reason,
        ]);

        // Update claim totals
        $this->bundleService->updateClaimTotals($treatment->claim);

        return $treatment->fresh();
    }

    /**
     * Get claim preview with structured sections
     */
    public function getClaimPreview(Claim $claim): array
    {
        $claim->load(['sections', 'treatments', 'diagnoses', 'admission', 'paCode']);

        return [
            'claim' => $claim,
            'sections' => [
                'A' => $claim->sections->where('section_type', 'A')->first(),
                'B' => $claim->sections->where('section_type', 'B')->first(),
                'C' => $claim->sections->where('section_type', 'C')->first(),
            ],
            'totals' => [
                'bundle_total' => $claim->bundle_total,
                'ffs_total' => $claim->ffs_total,
                'grand_total' => $claim->bundle_total + $claim->ffs_total,
            ],
            'compliance' => $claim->compliance_summary,
        ];
    }
}

