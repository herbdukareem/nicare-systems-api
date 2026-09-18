<?php

namespace App\Http\Controllers\Api;

use App\Exports\BhcpfMandeReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BhcpfMandeReportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from_date') ? ['after_or_equal:from_date'] : [])],
        ]);

        Log::info('bhcpf_mande_report_requested', [
            'user_id' => $request->user()?->getKey(),
            'filters' => $filters,
        ]);

        try {
            return (new BhcpfMandeReportExport($filters))->download();
        } catch (Throwable $exception) {
            Log::error('bhcpf_mande_report_failed', [
                'user_id' => $request->user()?->getKey(),
                'filters' => $filters,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
