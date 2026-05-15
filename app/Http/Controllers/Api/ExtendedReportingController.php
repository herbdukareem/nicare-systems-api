<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtendedReportingController extends Controller
{
    /** Map report types to service methods. */
    private const REPORTS = [
        'enrollment-summary'       => 'enrollmentSummary',
        'mobile-enrollment-activity'=> 'mobileEnrollmentActivity',
        'offline-sync-summary'     => 'offlineSyncSummary',
        'facility-utilization'     => 'facilityUtilization',
        'referral-preauth'         => 'referralPreauth',
        'admission'                => 'admission',
        'capitation'               => 'capitation',
        'financial-liability'      => 'financialLiability',
        'payment'                  => 'payment',
        'rejected-claims'          => 'rejectedClaims',
        'audit-activity'           => 'auditActivity',
        'user-activity'            => 'userActivity',
        'executive-summary'        => 'executiveSummary',
    ];

    public function __construct(private readonly ReportingService $service)
    {
    }

    /**
     * GET /api/v1/reports/{type}
     * Unified report endpoint.
     */
    public function __invoke(Request $request, string $type)
    {
        $method = self::REPORTS[$type] ?? null;

        if (!$method) {
            return response()->json([
                'success' => false,
                'message' => "Unknown report type: {$type}. Valid types: " . implode(', ', array_keys(self::REPORTS)),
            ], 404);
        }

        $filters = $request->only([
            'from_date', 'to_date', 'lga_id', 'facility_id', 'status',
            'format', 'page', 'per_page',
        ]);

        $format = strtolower($request->get('format', 'json'));

        // executive-summary is PDF only
        if ($type === 'executive-summary' && $format !== 'pdf') {
            $format = 'pdf';
        }

        $report = $this->service->{$method}($filters);

        return match ($format) {
            'pdf'   => $this->asPdf($type, $report),
            'excel' => $this->asExcel($type, $report),
            'csv'   => $this->asCsv($type, $report),
            default => $this->asJson($report),
        };
    }

    // -------------------------------------------------------------------------

    private function asJson(array $report): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $report]);
    }

    private function asPdf(string $type, array $report)
    {
        $view = view()->exists("reports.{$type}") ? "reports.{$type}" : 'reports.default';

        $pdf = Pdf::loadView($view, [
            'title'        => $report['title'],
            'generated_at' => $report['generated_at'],
            'generated_by' => $report['generated_by'],
            'filters'      => $report['filters'],
            'data'         => $report['data'],
        ])->setPaper('a4', 'landscape');

        $filename = str_replace(' ', '_', strtolower($report['title'])) . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    private function asExcel(string $type, array $report)
    {
        $rows  = $this->flattenData($report['data']);
        $heads = !empty($rows) ? array_keys((array) reset($rows)) : [];

        $exportable = new class ($rows, $heads, $report['title']) implements FromArray, WithHeadings, WithTitle {
            public function __construct(
                private array $rows,
                private array $heads,
                private string $sheetTitle
            ) {}

            public function array(): array
            {
                return array_map(fn ($r) => is_array($r) ? array_values($r) : (array) $r, $this->rows);
            }

            public function headings(): array
            {
                return array_map(fn ($h) => ucwords(str_replace('_', ' ', $h)), $this->heads);
            }

            public function title(): string
            {
                return substr($this->sheetTitle, 0, 31); // Excel sheet name max length
            }
        };

        $filename = str_replace(' ', '_', strtolower($report['title'])) . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download($exportable, $filename);
    }

    private function asCsv(string $type, array $report): StreamedResponse
    {
        $rows  = $this->flattenData($report['data']);
        $heads = !empty($rows) ? array_keys((array) reset($rows)) : [];

        $filename = str_replace(' ', '_', strtolower($report['title'])) . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($rows, $heads) {
            $out = fopen('php://output', 'w');
            if ($heads) {
                fputcsv($out, array_map(fn ($h) => ucwords(str_replace('_', ' ', $h)), $heads));
            }
            foreach ($rows as $row) {
                fputcsv($out, is_array($row) ? array_values($row) : array_values((array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Normalise nested data structures into a flat array of rows. */
    private function flattenData(mixed $data): array
    {
        if (is_array($data) && isset($data['kpis'])) {
            // Executive summary — flatten KPI array
            return array_map(fn ($k) => (array) $k, $data['kpis']);
        }

        if (is_array($data) && isset($data['batches'])) {
            return array_map(fn ($r) => (array) $r, $data['batches']);
        }

        if (is_array($data) && !isset($data[0])) {
            // Single associative row — wrap it
            return [$data];
        }

        return array_map(fn ($r) => (array) $r, (array) $data);
    }
}
