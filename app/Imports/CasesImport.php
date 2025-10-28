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

                // Validate row data
                $validator = Validator::make($rowData, [
                    'nicare_code' => 'required|string|max:255',
                    'case_description' => 'required|string|max:500',
                    'level_of_care' => 'required|in:Primary,Secondary,Tertiary',
                    'price' => 'required|numeric|min:0|max:999999999',
                    'group' => 'required|string|max:255',
                    'pa_required' => 'nullable|in:Yes,No,true,false,1,0,YES,NO',
                    'referable' => 'nullable|in:Yes,No,true,false,1,0,YES,NO',
                ]);

                if ($validator->fails()) {
                    $errors = $validator->errors()->all();
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode('; ', $errors);
                    continue;
                }

                // Check for duplicate nicare_code
                if (CaseRecord::where('nicare_code', $rowData['nicare_code'])->exists()) {
                    $this->errors[] = "Row " . ($index + 2) . ": Case with NiCare code '{$rowData['nicare_code']}' already exists";
                    continue;
                }

                // Validate price is a valid number
                if (!is_numeric($rowData['price']) || $rowData['price'] < 0) {
                    $this->errors[] = "Row " . ($index + 2) . ": Price must be a positive number";
                    continue;
                }

                // Convert boolean fields
                $paRequired = $this->convertToBoolean($rowData['pa_required'] ?? 'No');
                $referable = $this->convertToBoolean($rowData['referable'] ?? 'Yes');

                // Resolve case category id (by explicit value, by name, or by group mapping; fallback to 'Medical')
                $caseCategoryId = $this->resolveCaseCategoryId($rowData);

                // Create case record
                CaseRecord::create([
                    'nicare_code' => $rowData['nicare_code'],
                    'case_description' => $rowData['case_description'],
                    'level_of_care' => $rowData['level_of_care'],
                    'price' => (float) $rowData['price'],
                    'group' => $rowData['group'],
                    'case_category' => \App\Models\CaseRecord::CATEGORY_MAIN_CASE, // default to Main Case
                    'case_category_id' => $caseCategoryId,
                    'pa_required' => $paRequired,
                    'referable' => $referable,
                    'status' => true,
                    'created_by' => Auth::id(),
                ]);

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
        return $row->filter(function ($value) {
            return !empty(trim($value));
        })->isEmpty();
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

