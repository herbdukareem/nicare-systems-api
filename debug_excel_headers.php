<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Maatwebsite\Excel\Facades\Excel;

echo "=== Debugging Excel File Headers ===\n\n";

$filePath = 'C:/Projects/NiCare/nicare-systems-api/bundle_components_template (7).xlsx';

if (!file_exists($filePath)) {
    echo "❌ File not found: {$filePath}\n";
    exit;
}

echo "✅ File found: {$filePath}\n";
echo "File size: " . filesize($filePath) . " bytes\n\n";

// Read the file
$data = Excel::toArray(new \stdClass(), $filePath);

echo "Number of sheets: " . count($data) . "\n\n";

if (count($data) > 0) {
    $sheet = $data[0];
    echo "Rows in first sheet: " . count($sheet) . "\n\n";
    
    if (count($sheet) > 0) {
        echo "First row (headers):\n";
        print_r($sheet[0]);
        
        echo "\nNormalized headers (what Laravel Excel sees):\n";
        foreach ($sheet[0] as $index => $header) {
            $normalized = strtolower(str_replace([' ', '*', '-'], ['_', '', '_'], $header));
            $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
            $normalized = preg_replace('/_+/', '_', $normalized);
            $normalized = trim($normalized, '_');
            echo "  '{$header}' => '{$normalized}'\n";
        }
        
        echo "\n\nFirst 3 data rows:\n";
        for ($i = 1; $i <= min(3, count($sheet) - 1); $i++) {
            echo "\nRow {$i}:\n";
            print_r($sheet[$i]);
        }
    }
}

echo "\n=== Debug Complete ===\n";

