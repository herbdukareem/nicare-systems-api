<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BundleComponentsImport;

echo "=== Testing Bundle Components Import (Fixed) ===\n\n";

$filePath = 'C:/Projects/NiCare/nicare-systems-api/bundle_components_template (7).xlsx';

if (!file_exists($filePath)) {
    echo "❌ File not found: {$filePath}\n";
    exit;
}

echo "✅ File found\n\n";

try {
    $import = new BundleComponentsImport();

    echo "Starting import...\n";
    Excel::import($import, $filePath);
    echo "Import completed.\n\n";

    $errors = $import->getErrors();
    $imported = $import->getImported();

    echo "Imported: {$imported}\n";
    echo "Errors: " . count($errors) . "\n\n";

    if (count($errors) > 0) {
        echo "First 10 errors:\n";
        foreach (array_slice($errors, 0, 10) as $error) {
            echo "  - {$error}\n";
        }
    } else {
        echo "✅ No errors!\n";
    }

} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "\n";
} catch (\Error $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

