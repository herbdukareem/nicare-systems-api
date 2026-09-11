<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BulkEnrolleeNinUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkEnrolleeNinUpdateController extends Controller
{
    public function __invoke(Request $request, BulkEnrolleeNinUpdateService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bulk NIN update completed. Review the results for any skipped rows.',
            'data' => $service->update($request->file('file'), $request->user()),
        ]);
    }
}
