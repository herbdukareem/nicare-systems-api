<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

echo "=== Debugging Row Structure ===\n\n";

$filePath = 'C:/Projects/NiCare/nicare-systems-api/bundle_components_template (7).xlsx';

class DebugImport implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithHeadingRow
{
    public function collection(Collection $rows)
    {
        echo "Total rows received: " . $rows->count() . "\n\n";
        
        echo "First 5 rows:\n";
        foreach ($rows->take(5) as $index => $row) {
            echo "\nRow {$index}:\n";
            echo "  Has 'bundle_nicare_code': " . (isset($row['bundle_nicare_code']) ? 'YES' : 'NO') . "\n";
            echo "  Has 'component_nicare_code': " . (isset($row['component_nicare_code']) ? 'YES' : 'NO') . "\n";
            echo "  Keys: " . implode(', ', array_keys($row->toArray())) . "\n";
            
            if (isset($row['bundle_nicare_code'])) {
                echo "  bundle_nicare_code value: '{$row['bundle_nicare_code']}'\n";
                echo "  Is empty: " . (empty($row['bundle_nicare_code']) ? 'YES' : 'NO') . "\n";
            }
        }
        
        echo "\n\nLast 5 rows:\n";
        foreach ($rows->slice(-5) as $index => $row) {
            echo "\nRow {$index}:\n";
            echo "  Has 'bundle_nicare_code': " . (isset($row['bundle_nicare_code']) ? 'YES' : 'NO') . "\n";
            echo "  Has 'component_nicare_code': " . (isset($row['component_nicare_code']) ? 'YES' : 'NO') . "\n";
            echo "  Keys: " . implode(', ', array_keys($row->toArray())) . "\n";
        }
    }
}

try {
    Excel::import(new DebugImport(), $filePath);
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";

