<?php

namespace App\Http\Controllers\Api;

use App\Exports\BhcpfMandeReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BhcpfMandeReportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from_date') ? ['after_or_equal:from_date'] : [])],
        ]);

        return (new BhcpfMandeReportExport($filters))->download();
    }
}
