<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\EnrolleeDuplicateFlag;
use App\Models\EnrolleeImportBatch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EnrolleeImportService
{
    public function __construct(
        private readonly EnrolleeDuplicateDetectionService $duplicateService
    ) {
    }

    /**
     * Process the import file row by row and update batch counters.
     */
    public function processFile(EnrolleeImportBatch $batch): void
    {
        $path       = Storage::path($batch->file_path);
        $extension  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $rows   = $this->readFile($path, $extension);
        $errors = [];

        $total     = count($rows);
        $imported  = 0;
        $duplicates= 0;
        $failed    = 0;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-based + header offset

            // Basic validation
            $validator = Validator::make($row, [
                'first_name'    => ['required', 'string'],
                'last_name'     => ['required', 'string'],
                'date_of_birth' => ['required', 'date'],
                'gender'        => ['required'],
                'facility_id'   => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Row {$rowNum}: " . implode('; ', $validator->errors()->all());
                $failed++;
                continue;
            }

            // Duplicate detection
            $dupResult = $this->duplicateService->check($row);

            if ($dupResult['is_duplicate']) {
                // Create a duplicate flag
                try {
                    // We need a temporary enrollee to flag — skip creation
                    $errors[] = "Row {$rowNum}: Duplicate detected ({$dupResult['match_type']}) — matched enrollee ID {$dupResult['matched_enrollee_id']}.";
                } catch (\Throwable) {
                    // silent
                }
                $duplicates++;
                continue;
            }

            // Create enrollee
            try {
                Enrollee::create([
                    'first_name'           => $row['first_name'],
                    'last_name'            => $row['last_name'],
                    'middle_name'          => $row['middle_name'] ?? null,
                    'date_of_birth'        => $row['date_of_birth'],
                    'sex'                  => $row['gender'],
                    'phone'                => $row['phone'] ?? null,
                    'nin'                  => $row['nin'] ?? null,
                    'lga_id'               => $row['lga_id'] ?? null,
                    'ward_id'              => $row['ward_id'] ?? null,
                    'facility_id'          => $row['facility_id'],
                    'enrollee_type_id'     => $row['enrollee_type_id'] ?? null,
                    'funding_type_id'      => $row['funding_type_id'] ?? null,
                    'capitation_start_date'=> now(),
                    'created_by'           => $batch->uploaded_by,
                    'status'               => 'active',
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: " . $e->getMessage();
                $failed++;
            }
        }

        $batch->update([
            'total_rows'      => $total,
            'imported_count'  => $imported,
            'duplicate_count' => $duplicates,
            'failed_count'    => $failed,
            'errors'          => $errors ?: null,
            'status'          => 'completed',
        ]);
    }

    // -------------------------------------------------------------------------

    private function readFile(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->readCsv($path);
        }

        // xlsx via PhpSpreadsheet (Maatwebsite/Excel ships it)
        return $this->readXlsx($path);
    }

    private function readCsv(string $path): array
    {
        $rows    = [];
        $handle  = fopen($path, 'r');
        $headers = array_map('trim', fgetcsv($handle));

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, array_map('trim', $line));
            }
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $data        = $sheet->toArray(null, true, true, true);

        if (empty($data)) {
            return [];
        }

        $headers = array_values(array_shift($data));
        $rows    = [];

        foreach ($data as $row) {
            $values = array_values($row);
            if (array_filter($values)) {
                $rows[] = array_combine($headers, $values);
            }
        }

        return $rows;
    }
}
