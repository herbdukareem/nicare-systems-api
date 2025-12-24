<?php

namespace App\Imports;

use App\Models\BundleComponent;
use App\Models\CaseRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BundleComponentsImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];
    protected $imported = 0;

    public function collection(Collection $rows)
    {
        $this->errors = [];
        $this->imported = 0;

        // Cache for nicare_code lookups to avoid repeated queries
        $bundleCache = [];
        $componentCache = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 because index starts at 0 and we have header row

            try {
                // Validate required fields
                if (empty($row['bundle_nicare_code'])) {
                    $this->errors[] = "Row {$rowNumber}: Bundle NiCare Code is required";
                    continue;
                }

                if (empty($row['component_nicare_code'])) {
                    $this->errors[] = "Row {$rowNumber}: Component NiCare Code is required";
                    continue;
                }

                if (empty($row['max_quantity']) || !is_numeric($row['max_quantity'])) {
                    $this->errors[] = "Row {$rowNumber}: Max Quantity must be a valid number";
                    continue;
                }

                if (empty($row['item_type'])) {
                    $this->errors[] = "Row {$rowNumber}: Item Type is required";
                    continue;
                }

                // Lookup bundle by nicare_code (with caching)
                $bundleNicareCode = trim($row['bundle_nicare_code']);
                if (!isset($bundleCache[$bundleNicareCode])) {
                    $bundle = CaseRecord::where('nicare_code', $bundleNicareCode)
                        ->where('is_bundle', true)
                        ->first();
                    
                    if (!$bundle) {
                        $this->errors[] = "Row {$rowNumber}: Bundle with NiCare Code '{$bundleNicareCode}' not found or is not a bundle";
                        continue;
                    }
                    
                    $bundleCache[$bundleNicareCode] = $bundle;
                } else {
                    $bundle = $bundleCache[$bundleNicareCode];
                }

                // Lookup component by nicare_code (with caching)
                $componentNicareCode = trim($row['component_nicare_code']);
                if (!isset($componentCache[$componentNicareCode])) {
                    $component = CaseRecord::where('nicare_code', $componentNicareCode)->first();
                    
                    if (!$component) {
                        $this->errors[] = "Row {$rowNumber}: Component with NiCare Code '{$componentNicareCode}' not found";
                        continue;
                    }
                    
                    $componentCache[$componentNicareCode] = $component;
                } else {
                    $component = $componentCache[$componentNicareCode];
                }

                // Check for duplicate
                $exists = BundleComponent::where('service_bundle_id', $bundle->id)
                    ->where('case_record_id', $component->id)
                    ->exists();

                if ($exists) {
                    $this->errors[] = "Row {$rowNumber}: Component '{$componentNicareCode}' already exists in bundle '{$bundleNicareCode}'";
                    continue;
                }

                // Create bundle component
                DB::transaction(function () use ($bundle, $component, $row) {
                    BundleComponent::create([
                        'service_bundle_id' => $bundle->id,
                        'case_record_id' => $component->id,
                        'max_quantity' => (int) $row['max_quantity'],
                        'item_type' => strtoupper(trim($row['item_type'])),
                    ]);
                });

                $this->imported++;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImported(): int
    {
        return $this->imported;
    }
}

