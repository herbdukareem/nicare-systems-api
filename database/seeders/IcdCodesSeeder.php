<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class IcdCodesSeeder extends Seeder
{
    public function run(): void
    {
        $url = 'https://raw.githubusercontent.com/argmonster/icd10-sql/refs/heads/master/data/t_ICD-10_ICD-10_with_GEM_AXIS.tab';

        // Fetch content directly (no file saved)
        $resp = Http::timeout(300)->retry(3, 2000)->get($url);

        if (!$resp->successful()) {
            throw new \RuntimeException("Failed to fetch ICD data. HTTP status: {$resp->status()}");
        }

        $body = $resp->body();
        if (trim($body) === '') {
            throw new \RuntimeException("ICD data body is empty.");
        }

        DB::disableQueryLog();

        // Clean import (optional)
        DB::table('icdcodes')->truncate();

        $lines = preg_split("/\r\n|\n|\r/", $body);

        // Header row
        $headerLine = $this->firstNonEmpty($lines);
        if ($headerLine === null) {
            throw new \RuntimeException("No header row found.");
        }

        // Expected columns:
        // UniqID    ICD-10_Description    ICD-9_Description
        // We will map by position for consistency:
        $codeIdx = 0;
        $icd10Idx = 1;
        $icd9Idx  = 2;

        $batch = [];
        $batchSize = 1500;

        // Start after header line index
        $startIndex = array_search($headerLine, $lines, true);
        for ($i = $startIndex + 1; $i < count($lines); $i++) {
            $line = trim((string)$lines[$i]);
            if ($line === '') continue;

            $cols = preg_split("/\t/", $line);

            $code = trim((string)($cols[$codeIdx] ?? ''));
            if ($code === '') continue;

            $icd10 = $this->nullIfEmpty($cols[$icd10Idx] ?? null);
            $icd9  = $this->nullIfEmpty($cols[$icd9Idx] ?? null);

            $batch[] = [
                'code' => $code,
                'icd10_description' => $icd10,
                'icd9_description' => $icd9,
                'parent_code' => $this->inferParentCode($code),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    private function upsertBatch(array $batch): void
    {
        DB::table('icdcodes')->upsert(
            $batch,
            ['code'],
            ['icd10_description', 'icd9_description', 'parent_code', 'updated_at']
        );
    }

    private function firstNonEmpty(array $lines): ?string
    {
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line !== '') return $line;
        }
        return null;
    }

    private function nullIfEmpty($value): ?string
    {
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }

    private function inferParentCode(string $code): ?string
    {
        $code = trim($code);
        if (strlen($code) <= 3) return null;
        return substr($code, 0, 3);
    }
}
