<?php

namespace App\Imports;

use App\Models\TariffItem;
use App\Models\CaseRecord;
use App\Models\ServiceType;
use App\Models\CaseType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TariffItemsImport implements ToCollection, WithHeadingRow
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

                // Validate row data
                $validator = Validator::make($rowData, [
                    'casecode' => 'required|string',
                    'casename' => 'required|string',
                    'casecategory' => 'required|string',
                    'tarrifitem' => 'required|string|max:255',
                    'price' => 'required|numeric|min:0',
                    'casetype' => 'required|string'
                ]);

                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Find case
                $caseRecord = $this->findCase($rowData['casecode'], $rowData['casename']);
                
                if (!$caseRecord) {
                    $this->errors[] = "Row " . ($index + 2) . ": Could not find case";
                    continue;
                }

                // Find service type
                $serviceType = $this->findServiceType($rowData['casecategory']);
                
                if (!$serviceType) {
                    $this->errors[] = "Row " . ($index + 2) . ": Invalid service type '{$rowData['casecategory']}'";
                    continue;
                }

                // Find case type
                $caseType = $this->findCaseType($rowData['casetype']);
                
                if (!$caseType) {
                    $this->errors[] = "Row " . ($index + 2) . ": Invalid case type '{$rowData['casetype']}'";
                    continue;
                }

                // Check for duplicate tariff item
                $exists = TariffItem::where('case_id', $caseRecord->id)
                    ->where('service_type_id', $serviceType->id)
                    ->where('tariff_item', $rowData['tarrifitem'])
                    ->where('case_type_id', $caseType->id)
                    ->exists();

                if ($exists) {
                    $this->errors[] = "Row " . ($index + 2) . ": Tariff item already exists";
                    continue;
                }

                // Create tariff item
                TariffItem::create([
                    'case_id' => $caseRecord->id,
                    'service_type_id' => $serviceType->id,
                    'tariff_item' => $rowData['tarrifitem'],
                    'price' => (float) $rowData['price'],
                    'case_type_id' => $caseType->id,
                    'status' => true
                ]);

                $this->importedCount++;

            } catch (\Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
    }

    /**
     * Find case
     */
    private function findCase(string $code, string $name): ?CaseRecord
    {
        // Try to find by code first
        $case_record = CaseRecord::where('nicare_code', $code)->first();
        
        if ($case_record) {
            return $case_record;
        }

        // Try to find by name
        $case_record = CaseRecord::where('service_description', $name)->first();
        
        if ($case_record) {
            return $case_record;
        }

            return null;
    }

    /**
     * Find service type by name
     */
    private function findServiceType(string $name): ?ServiceType
    {
        // Map common variations
        $mappings = [
            'Professional Fees' => ['professional fees', 'professional fee', 'prof fees', 'prof fee'],
            'Hospital Stay' => ['hospital stay', 'hospital', 'accommodation'],
            'Laboratory Investigations' => ['laboratory investigations', 'laboratory', 'lab investigations', 'lab'],
            'Diagnostic Investigations' => ['diagnostic investigations', 'diagnostic', 'imaging', 'other investigations'],
            'Other Investigations' => ['other investigations', 'other investigation'],
            'Other Fees' => ['other fees', 'other fee', 'miscellaneous']
        ];

        $nameLower = strtolower(trim($name));

        foreach ($mappings as $serviceTypeName => $variations) {
            if (in_array($nameLower, $variations)) {
                return ServiceType::where('name', $serviceTypeName)->first();
            }
        }

        // Try exact match
        return ServiceType::where('name', $name)->first();
    }

    /**
     * Find case type by name
     */
    private function findCaseType(string $name): ?CaseType
    {
        $nameLower = strtolower(trim($name));

        // Map common variations
        if (in_array($nameLower, ['surgical', 'surgery'])) {
            return CaseType::where('name', 'Surgical')->first();
        }

        if (in_array($nameLower, ['non-surgical', 'non surgical', 'nonsurgical', 'medical'])) {
            return CaseType::where('name', 'Non-surgical')->first();
        }

        // Try exact match
        return CaseType::where('name', $name)->first();
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
     * Get imported count
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

