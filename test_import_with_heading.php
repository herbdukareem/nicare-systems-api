<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BundleComponentsImport;

echo "=== Testing Import with WithHeadingRow ===\n\n";

$filePath = 'C:/Projects/NiCare/nicare-systems-api/bundle_components_template (7).xlsx';

if (!file_exists($filePath)) {
    echo "❌ File not found: {$filePath}\n";
    exit;
}

echo "✅ File found\n\n";

// Create a test import class to see what data we get
class TestImport implements \Maatwebsite\Excel\Concerns\ToCollection, \Maatwebsite\Excel\Concerns\WithHeadingRow
{
    public function collection(\Illuminate\Support\Collection $rows)
    {
        echo "Total rows: " . $rows->count() . "\n\n";
        
        echo "First 3 rows:\n";
        foreach ($rows->take(3) as $index => $row) {
            echo "\nRow {$index}:\n";
            echo "  Type: " . gettype($row) . "\n";
            if (is_array($row) || $row instanceof \Illuminate\Support\Collection) {
                echo "  Keys: " . implode(', ', array_keys($row->toArray())) . "\n";
                echo "  Data:\n";
                foreach ($row as $key => $value) {
                    echo "    '{$key}' => '{$value}'\n";
                }
            }
        }
    }
}

try {
    Excel::import(new TestImport(), $filePath);
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

