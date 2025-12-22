<?php

namespace App\Imports;

use App\Models\CaseRecord;
use App\Models\CaseCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CasesImport implements ToCollection, WithHeadingRow
{
    private int $importedCount = 0;
    private array $errors = [];

    public function collection(Collection $rows)
    {
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
                    'bundle_price' => 'nullable|numeric|min:0|max:999999999',
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
                    if (empty($rowData['bundle_price']) || !is_numeric($rowData['bundle_price'])) {
                        $this->errors[] = "Row " . ($index + 2) . ": Bundle Price is required for bundle cases";
                        continue;
                    }
                    if (empty($rowData['diagnosis_icd10'])) {
                        $this->errors[] = "Row " . ($index + 2) . ": ICD-10 Code is required for bundle cases";
                        continue;
                    }
                }

                // Generate NiCare code automatically
                $nicareCode = CaseRecord::generateNiCareCode(
                    $rowData['case_name'],
                    $rowData['level_of_care']
                );

                // Determine price based on whether it's a bundle or FFS service
                // For bundles: price can be 0 (bundle_price is used instead)
                // For FFS: price is required
                $price = 0; // Default to 0
                if ($isBundle) {
                    // For bundles, price is typically 0 (bundle_price is used)
                    $price = !empty($rowData['price']) ? (float) $rowData['price'] : 0;
                } else {
                    // For FFS services, price is required
                    if (empty($rowData['price'])) {
                        $this->errors[] = "Row " . ($index + 2) . ": Price is required for FFS services";
                        continue;
                    }
                    $price = (float) $rowData['price'];
                }

                // Prepare case record data
                $caseData = [
                    'case_name' => $rowData['case_name'],
                    'nicare_code' => $nicareCode,
                    'service_description' => $rowData['service_description'],
                    'level_of_care' => $rowData['level_of_care'],
                    'price' => $price,
                    'group' => $rowData['group'],
                    // Note: case_category column doesn't exist in database, skip it
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
                    $caseData['bundle_price'] = (float) $rowData['bundle_price'];
                    $caseData['diagnosis_icd10'] = trim($rowData['diagnosis_icd10']);
                }

                // Check for duplicate case_name
                $existingCase = CaseRecord::where('case_name', $rowData['case_name'])->first();
                if ($existingCase) {
                    $this->errors[] = "Row " . ($index + 2) . ": Case with name '{$rowData['case_name']}' already exists";
                    continue;
                }

                // Create case record
                CaseRecord::create($caseData);

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

        // Clean up bundle_price strings like "₦50,000" -> "50000"
        if (isset($normalized['bundle_price']) && is_string($normalized['bundle_price'])) {
            $normalized['bundle_price'] = preg_replace('/[^0-9.]/', '', $normalized['bundle_price']);
            // If bundle_price becomes empty after cleaning, set to null
            if ($normalized['bundle_price'] === '') {
                $normalized['bundle_price'] = null;
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
}

