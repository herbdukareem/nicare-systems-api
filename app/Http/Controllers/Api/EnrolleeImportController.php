<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEnrolleeImportJob;
use App\Models\EnrolleeImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrolleeImportController extends Controller
{
    /**
     * POST /api/enrollees/import
     * Upload a CSV/XLSX file and queue processing.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->store('enrollee-imports', 'local');

        $batch = EnrolleeImportBatch::create([
            'uploaded_by' => auth()->id(),
            'file_path'   => $path,
            'status'      => 'pending',
        ]);

        ProcessEnrolleeImportJob::dispatch($batch);

        return response()->json([
            'success'  => true,
            'message'  => 'File uploaded and queued for processing.',
            'batch_id' => $batch->id,
        ], 202);
    }

    /**
     * GET /api/enrollees/import/{batch}
     * Return batch status and counts.
     */
    public function status(EnrolleeImportBatch $batch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $batch->load('uploader'),
        ]);
    }

    /**
     * GET /api/enrollees/import-template
     * Return a streamed CSV download with the expected column headers.
     */
    public function template(): StreamedResponse
    {
        $headers = [
            'first_name', 'last_name', 'middle_name', 'date_of_birth',
            'gender', 'phone', 'nin', 'lga_id', 'ward_id', 'facility_id',
            'enrollee_type_id', 'funding_type_id',
        ];

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            // Example row
            fputcsv($out, ['John', 'Doe', 'Middle', '1990-01-01', 'male', '08012345678', '', '1', '1', '1', '1', '1']);
            fclose($out);
        }, 'enrollee_import_template.csv', ['Content-Type' => 'text/csv']);
    }
}
