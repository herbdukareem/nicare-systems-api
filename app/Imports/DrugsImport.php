<?php

namespace App\Imports;

use App\Models\Drug;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DrugsImport implements ToCollection, WithHeadingRow
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
                    'drug_name' => 'required|string|max:255',
                    'drug_dosage_form' => 'required|string|max:255',
                    'drug_strength' => 'nullable|string|max:255',
                    'drug_presentation' => 'required|string|max:255',
                    'drug_unit_price' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    $this->errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Check for duplicate nicare_code
                if (Drug::where('nicare_code', $row['nicare_code'])->exists()) {
                    $this->errors[] = "Row " . ($index + 2) . ": Drug with NiCare code '{$row['nicare_code']}' already exists";
                    continue;
                }

                // Create drug record
                Drug::create([
                    'nicare_code' => $row['nicare_code'],
                    'drug_name' => $row['drug_name'],
                    'drug_dosage_form' => $row['drug_dosage_form'],
                    'drug_strength' => $row['drug_strength'] ?? null,
                    'drug_presentation' => $row['drug_presentation'],
                    'drug_unit_price' => (float) $row['drug_unit_price'],
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
            'drug_name',
            'drug_dosage_form',
            'drug_strength',
            'drug_presentation',
            'drug_unit_price'
        ];
    }
}
