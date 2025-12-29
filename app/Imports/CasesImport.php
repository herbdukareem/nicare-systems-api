<?php

namespace App\Imports;

use App\Models\CaseRecord;
use App\Models\CaseCategory;
use App\Models\DrugDetail;
use App\Models\LaboratoryDetail;
use App\Models\RadiologyDetail;
use App\Models\ProfessionalServiceDetail;
use App\Models\ConsultationDetail;
use App\Models\ConsumableDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CasesImport implements ToCollection, WithHeadingRow
{
    private int $importedCount = 0;
    private array $errors = [];
    private ?string $detectedDetailType = null;

    public function collection(Collection $rows)
    {
        // Detect detail type from first row's columns
        if ($rows->isNotEmpty()) {
            $this->detectedDetailType = $this->detectDetailType($rows->first());
        }

        foreach ($rows as $index => $row) {
            try {
                // Skip empty rows
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                // Trim all string values
                $rowData = $row->map(function ($value) {
                    return is_string($value) ? trim($value) : $value;
                })->toArray();

                // Normalize keys from heading row (handle Price (₦) -> price)
                $rowData = $this->normalizeRowKeys($rowData);

                // Convert boolean fields first to determine validation rules
                $isBundle = $this->convertToBoolean($rowData['is_bundle'] ?? 'No');

                // Validate row data
                $validator = Validator::make($rowData, [
                    'case_name' => 'required|string|max:255',
                    'service_description' => 'required|string|max:500',
                    'level_of_care' => 'required|in:Primary,Secondary,Tertiary',
                    'price' => 'nullable|numeric|min:0|max:999999999', // Optional for bundles
                    'group' => 'required|string|max:255',
                    'pa_required' => 'nullable|in:Yes,No,true,false,1,0,YES,NO',
                    'referable' => 'nullable|in:Yes,No,true,false,1,0,YES,NO',
                    'is_bundle' => 'nullable|in:Yes,No,true,false,1,0,YES,NO',
                    'diagnosis_icd10' => 'nullable|string|max:20',
                ]);

                if ($validator->fails()) {
                    $errors = $validator->errors()->all();
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode('; ', $errors);
                    continue;
                }

                // Convert other boolean fields
                $paRequired = $this->convertToBoolean($rowData['pa_required'] ?? 'No');
                $referable = $this->convertToBoolean($rowData['referable'] ?? 'Yes');

                // Validate bundle-specific fields
                if ($isBundle) {
                   
                    if (empty($rowData['diagnosis_icd10'])) {
                        $this->errors[] = "Row " . ($index + 2) . ": ICD-10 Code is required for bundle cases";
                        continue;
                    }
                }

                //  if ((empty($rowData['price']) || !is_numeric($rowData['price'])) && (empty($rowData['bundle_price']) || !is_numeric($rowData['bundle_price']))) {
                //         $this->errors[] = "Row " . ($index + 2) . ": Price is required for  cases";
                //         continue;
                //     }

                // Generate NiCare code automatically
                $nicareCode = CaseRecord::generateNiCareCode(
                    $rowData['case_name'],
                    $rowData['level_of_care']
                );

                // Determine price based on whether it's a bundle or FFS service
                $price = isset($rowData['bundle_price']) ?? $rowData['price'] ?? 0;
                $price = !empty($price) ? (float) $price : 0;

                // Prepare case record data
                $caseData = [
                    'case_name' => $rowData['case_name'],
                    'nicare_code' => $nicareCode,
                    'service_description' => $rowData['service_description'],
                    'level_of_care' => $rowData['level_of_care'],
                    'price' => $price,
                    'group' => $rowData['group'],
                    'pa_required' => $paRequired,
                    'referable' => $referable,
                    'is_bundle' => $isBundle,
                    'status' => true,
                    'created_by' => Auth::id(),
                ];

                // Resolve case category id only if the table exists
                try {
                    $caseCategoryId = $this->resolveCaseCategoryId($rowData);
                    if ($caseCategoryId) {
                        $caseData['case_category_id'] = $caseCategoryId;
                    }
                } catch (\Exception $e) {
                    // Skip case category if table doesn't exist
                    // This is optional field anyway
                }

                // Add bundle-specific fields if it's a bundle
                if ($isBundle) {
                    $caseData['bundle_price'] = (float) $price;
                    $caseData['diagnosis_icd10'] = trim($rowData['diagnosis_icd10']);
                }else{
                       $caseData['price'] = (float) $price;
                }

                // Check for duplicate case_name if not drug
                if ($this->detectedDetailType !== 'Drug') {
                        $existingCase = CaseRecord::where('case_name', $rowData['case_name'])->first();
                        if ($existingCase) {
                            $this->errors[] = "Row " . ($index + 2) . ": Case with name '{$rowData['case_name']}' already exists";
                            continue;
                        }

                    }else{
                        if( $this->detectedDetailType === 'Drug'){
                         // check for duplicate drug combination (name, dosage form, strength, presentation, pack description)
                            $existingDrug = DrugDetail::where('generic_name', $rowData['generic_name'])
                                // ->where('brand_name', $rowData['brand_name'] ?? null)
                                ->where('dosage_form', $rowData['dosage_form'] ?? null)
                                ->where('strength', $rowData['strength'] ?? null)
                                ->where('pack_description', $rowData['pack_description'] ?? null)
                                ->first();
                                

                            if ($existingDrug) {
                                 $this->errors[] = "Row " . ($index + 2) . ": Drug with same combination'{$rowData['case_name']}' already exists";
                                 continue;
                            } 
                        }
                    }

                // Create case record and detail record in a transaction
                DB::transaction(function () use ($caseData, $rowData, $index, $isBundle) {
                    // Create case record
                    $caseRecord = CaseRecord::create($caseData);

                    // Create detail record if detail type was detected from template
                    if ($this->detectedDetailType && !$isBundle) {
                        $this->createDetailRecord($caseRecord, $this->detectedDetailType, $rowData, $index);
                    }
                });

                $this->importedCount++;

            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    /**
     * Check if row is empty
     */
    private function isEmptyRow(Collection $row): bool
    {
        // Check if all cells are empty
        $nonEmptyCells = $row->filter(function ($value) {
            if ($value === null || $value === '') {
                return false;
            }
            if (is_string($value) && trim($value) === '') {
                return false;
            }
            return true;
        });

        return $nonEmptyCells->isEmpty();
    }

    /**
     * Convert string values to boolean
     */
    private function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        $value = strtolower(trim((string) $value));



        return in_array($value, ['yes', 'true', '1', 'y', 'on']);
    }

    /**
     * Normalize row keys produced by WithHeadingRow's slugging, e.g.
     * "Price (₦) *" slugged to price_n; map to 'price'. Also sanitize price values.
     */
    private function normalizeRowKeys(array $row): array
    {
        $normalized = $row;

        // Map case_description to service_description
        if (isset($normalized['case_description']) && !isset($normalized['service_description'])) {
            $normalized['service_description'] = $normalized['case_description'];
        }

        // Normalize price field
        if (!array_key_exists('price', $normalized)) {
            foreach ($normalized as $key => $value) {
                if (is_string($key) && preg_match('/^price($|_)/', $key)) {
                    $normalized['price'] = $value;
                    break;
                }
            }
        }

        // Clean up price strings like "₦1,000" -> "1000"
        if (isset($normalized['price']) && is_string($normalized['price'])) {
            $normalized['price'] = preg_replace('/[^0-9.]/', '', $normalized['price']);
            // If price becomes empty after cleaning, set to null
            if ($normalized['price'] === '') {
                $normalized['price'] = null;
            }
        }

        // Normalize bundle_price field
        if (!array_key_exists('bundle_price', $normalized)) {
            foreach ($normalized as $key => $value) {
                if (is_string($key) && preg_match('/^bundle[_ ]?price($|_)/i', $key)) {
                    $normalized['bundle_price'] = $value;
                    break;
                }
            }
        }

      

        // Normalize ICD-10 code field
        if (!array_key_exists('diagnosis_icd10', $normalized)) {
            foreach ($normalized as $key => $value) {
                if (is_string($key) && preg_match('/^(icd[_-]?10|diagnosis[_ ]?icd10)($|_)/i', $key)) {
                    $normalized['diagnosis_icd10'] = $value;
                    break;
                }
            }
        }

        // Normalize is_bundle field
        if (!array_key_exists('is_bundle', $normalized)) {
            foreach ($normalized as $key => $value) {
                if (is_string($key) && preg_match('/^is[_ ]?bundle$/i', $key)) {
                    $normalized['is_bundle'] = $value;
                    break;
                }
            }
        }

        // Normalize optional case category columns if present
        foreach (['case_category', 'case_category_name', 'case_category_id'] as $candidate) {
            if (!array_key_exists($candidate, $normalized)) {
                foreach ($normalized as $key => $value) {
                    if (is_string($key) && preg_match('/^case[_ ]?category(_name|_id)?$/i', $key)) {
                        $normalized[$candidate] = $value;
                        break 2;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Resolve case_category_id to persist
     */
    private function resolveCaseCategoryId(array $row): ?int
    {
        // 1) If explicit numeric id provided and exists, use it
        if (!empty($row['case_category_id']) && is_numeric($row['case_category_id'])) {
            $id = (int) $row['case_category_id'];
            if (CaseCategory::where('id', $id)->exists()) {
                return $id;
            }
        }

        // 2) If a category name is provided, map by name
        $nameCandidates = [];
        if (!empty($row['case_category']) && is_string($row['case_category'])) $nameCandidates[] = $row['case_category'];
        if (!empty($row['case_category_name']) && is_string($row['case_category_name'])) $nameCandidates[] = $row['case_category_name'];
        foreach ($nameCandidates as $name) {
            $found = CaseCategory::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
            if ($found) {
                return $found->id;
            }
        }

        // 3) Derive from Group
        $group = isset($row['group']) ? strtoupper(trim((string)$row['group'])) : '';
        $map = [
            'LABORATORY' => 'Diagnostic',
            'RADIOLOGY' => 'Diagnostic',
            'EMERGENCY SERVICES' => 'Emergency',
            'PAEDIATRICS' => 'Pediatrics',
            'OBSTETRICS & GYNAECOLOGY' => 'Obstetrics & Gynecology',
            'SURGERY' => 'Surgical',
            'GENERAL CONSULTATION' => 'Medical',
            'INTERNAL MEDICINE (PRV)' => 'Medical',
            'PHARMACY' => 'Medical',
        ];
        if ($group && isset($map[$group])) {
            $found = CaseCategory::where('name', $map[$group])->first();
            if ($found) {
                return $found->id;
            }
        }

        // 4) Fallback to 'Medical' if it exists, else first active category
        $fallback = CaseCategory::where('name', 'Medical')->first()
            ?? CaseCategory::active()->orderBy('id')->first();

        return $fallback?->id;
    }


    /**
     * Get the number of imported records
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get import errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get expected headers for validation
     */
    public function getExpectedHeaders(): array
    {
        return [
            'nicare_code',
            'case_description',
            'level_of_care',
            'price',
            'group',
            'pa_required',
            'referable'
        ];
    }

    /**
     * Detect detail type from column headers
     */
    private function detectDetailType($row): ?string
    {
        $columns = array_keys($row->toArray());

        // Check for bundle-specific columns
        if (in_array('price', $columns) && in_array('diagnosis_icd10', $columns)) {
            return 'Bundle';
        }

        // Check for drug-specific columns
        if (in_array('generic_name', $columns) || in_array('drug_class', $columns) || in_array('nafdac_number', $columns)) {
            return 'Drug';
        }

        // Check for laboratory-specific columns
        if (in_array('test_name', $columns) || in_array('test_code', $columns) || in_array('specimen_type', $columns)) {
            return 'Laboratory';
        }

        // Check for radiology-specific columns
        if (in_array('examination_name', $columns) || in_array('examination_code', $columns) || in_array('modality', $columns)) {
            return 'Radiology';
        }

        // Check for professional service-specific columns
        if (in_array('service_name', $columns) || in_array('service_code', $columns) || in_array('anesthesia_required', $columns)) {
            return 'ProfessionalService';
        }

        // Check for consultation-specific columns
        if (in_array('consultation_type', $columns) || in_array('consultation_mode', $columns)) {
            return 'Consultation';
        }

        // Check for consumable-specific columns
        if (in_array('item_name', $columns) || in_array('item_code', $columns) || in_array('sterile', $columns)) {
            return 'Consumable';
        }

        return null; // General case without detail type
    }

    /**
     * Create detail record based on detail type
     */
    private function createDetailRecord(CaseRecord $caseRecord, string $detailType, array $rowData, int $index): void
    {
        $detail = null;

        switch ($detailType) {
            case 'Drug':
                $detail = $this->createDrugDetail($rowData, $index);
                break;
            case 'Laboratory':
                $detail = $this->createLaboratoryDetail($rowData, $index);
                break;
            case 'Radiology':
                $detail = $this->createRadiologyDetail($rowData, $index);
                break;
            case 'ProfessionalService':
                $detail = $this->createProfessionalServiceDetail($rowData, $index);
                break;
            case 'Consultation':
                $detail = $this->createConsultationDetail($rowData, $index);
                break;
            case 'Consumable':
                $detail = $this->createConsumableDetail($rowData, $index);
                break;
            default:
                throw new \Exception("Invalid detail type: {$detailType}");
        }

        // Link the detail to the case record
        if ($detail) {
            $caseRecord->update([
                'detail_type' => get_class($detail),
                'detail_id' => $detail->id,
            ]);
        }
    }

    /**
     * Create Drug detail record
     */
    private function createDrugDetail(array $rowData, int $index): DrugDetail
    {
        // Validate required fields
        if (empty($rowData['generic_name'])) {
            throw new \Exception("Drug: Generic Name is required for Drug detail type");
        }

         

        return DrugDetail::create([
            'generic_name' => $rowData['generic_name'],
            'brand_name' => $rowData['brand_name'] ?? null,
            'dosage_form' => $rowData['dosage_form'] ?? null,
            'strength' => $rowData['strength'] ?? null,
            'pack_description' => $rowData['pack_description'] ?? null,
            'route_of_administration' => $rowData['route_of_administration'] ?? null,
            'manufacturer' => $rowData['manufacturer'] ?? null,
            'drug_class' => $rowData['drug_class'] ?? null,
            'nafdac_number' => $rowData['nafdac_number'] ?? null,
        ]);
    }

    /**
     * Create Laboratory detail record
     */
    private function createLaboratoryDetail(array $rowData, int $index): LaboratoryDetail
    {
        // Validate required fields
        if (empty($rowData['test_name'])) {
            throw new \Exception("Lab: Test Name is required for Laboratory detail type");
        }

        return LaboratoryDetail::create([
            'test_name' => $rowData['test_name'],
            'test_code' => $rowData['test_code'] ?? null,
            'specimen_type' => $rowData['specimen_type'] ?? null,
            'test_category' => $rowData['test_category'] ?? null,
            'turnaround_time' => !empty($rowData['turnaround_time_hours']) ? (int) $rowData['turnaround_time_hours'] : null,
            'fasting_required' => $this->convertToBoolean($rowData['fasting_required'] ?? 'No'),
        ]);
    }

    /**
     * Create Radiology detail record
     */
    private function createRadiologyDetail(array $rowData, int $index): RadiologyDetail
    {
        // Validate required fields
        if (empty($rowData['examination_name'])) {
            throw new \Exception("Radiology: Exam Name is required for Radiology detail type");
        }

        return RadiologyDetail::create([
            'examination_name' => $rowData['examination_name'],
            'examination_code' => $rowData['examination_code'] ?? null,
            'modality' => $rowData['modality'] ?? null,
            'body_part' => $rowData['body_part'] ?? null,
            'contrast_required' => $this->convertToBoolean($rowData['contrast_required'] ?? 'No'),
            'pregnancy_safe' => $this->convertToBoolean($rowData['pregnancy_safe'] ?? 'No'),
        ]);
    }

    /**
     * Create Professional Service detail record
     */
    private function createProfessionalServiceDetail(array $rowData, int $index): ProfessionalServiceDetail
    {
        // Validate required fields
        if (empty($rowData['service_name'])) {
            throw new \Exception("ProfService: Service Name is required for Professional Service detail type");
        }

        return ProfessionalServiceDetail::create([
            'service_name' => $rowData['service_name'],
            'service_code' => $rowData['service_code'] ?? null,
            'specialty' => $rowData['specialty'] ?? null,
            'duration_minutes' => !empty($rowData['duration_minutes']) ? (int) $rowData['duration_minutes'] : null,
            'provider_type' => $rowData['provider_type'] ?? null,
            'anesthesia_required' => $this->convertToBoolean($rowData['anesthesia_required'] ?? 'No'),
        ]);
    }

    /**
     * Create Consultation detail record
     */
    private function createConsultationDetail(array $rowData, int $index): ConsultationDetail
    {
        // Validate required fields
        if (empty($rowData['consultation_type'])) {
            throw new \Exception("Consultation: Type is required for Consultation detail type");
        }

        return ConsultationDetail::create([
            'consultation_type' => $rowData['consultation_type'],
            'specialty' => $rowData['consultation_specialty'] ?? null,
            'provider_level' => $rowData['consultation_provider_level'] ?? null,
            'duration_minutes' => !empty($rowData['consultation_duration']) ? (int) $rowData['consultation_duration'] : null,
            'consultation_mode' => $rowData['consultation_mode'] ?? null,
        ]);
    }

    /**
     * Create Consumable detail record
     */
    private function createConsumableDetail(array $rowData, int $index): ConsumableDetail
    {
        // Validate required fields
        if (empty($rowData['item_name'])) {
            throw new \Exception("Consumable: Item Name is required for Consumable detail type");
        }

        return ConsumableDetail::create([
            'item_name' => $rowData['item_name'],
            'item_code' => $rowData['item_code'] ?? null,
            'category' => $rowData['category'] ?? null,
            'unit_of_measure' => $rowData['unit_of_measure'] ?? null,
            'units_per_pack' => !empty($rowData['units_per_pack']) ? (int) $rowData['units_per_pack'] : null,
            'sterile' => $this->convertToBoolean($rowData['sterile'] ?? 'No'),
        ]);
    }
}

