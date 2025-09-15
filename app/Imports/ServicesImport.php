<?php

namespace App\Imports;

use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ServicesImport implements ToCollection, WithHeadingRow
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

                // Validate row data
                $validator = Validator::make($row->toArray(), [
                    'nicare_code' => 'required|string|max:255',
                    'service_description' => 'required|string',
                    'level_of_care' => 'required|in:Primary,Secondary,Tertiary',
                    'price' => 'required|numeric|min:0',
                    'group' => 'required|string|max:255',
                    'pa_required' => 'nullable|in:Yes,No,true,false,1,0',
                    'referable' => 'nullable|in:Yes,No,true,false,1,0',
                ]);

                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Check for duplicate nicare_code
                if (Service::where('nicare_code', $row['nicare_code'])->exists()) {
                    $this->errors[] = "Row " . ($index + 2) . ": Service with NiCare code '{$row['nicare_code']}' already exists";
                    continue;
                }

                // Convert boolean fields
                $paRequired = $this->convertToBoolean($row['pa_required'] ?? 'No');
                $referable = $this->convertToBoolean($row['referable'] ?? 'Yes');

                // Create service record
                Service::create([
                    'nicare_code' => $row['nicare_code'],
                    'service_description' => $row['service_description'],
                    'level_of_care' => $row['level_of_care'],
                    'price' => (float) $row['price'],
                    'group' => $row['group'],
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

        $value = strtolower(trim($value));
        
        return in_array($value, ['yes', 'true', '1', 'y']);
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
            'service_description',
            'level_of_care',
            'price',
            'group',
            'pa_required',
            'referable'
        ];
    }
}
