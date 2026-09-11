<?php

namespace App\Exports;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuntimeException;
use SplTempFileObject;
use stdClass;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BhcpfMandeReportExport
{
    private const BATCH_SIZE = 2000;

    public const HEADINGS = [
        'SN', 'STATE', 'INSURANCE ID', 'PROGRAM', 'FIRST NAME', 'MIDDLE NAME',
        'LAST NAME', 'DOB', 'GENDER', 'ADDRESS', 'LGA', 'WARD', 'COMMUNITY',
        'OCCUPATION', 'NIN', 'PHONE', 'EMAIL', 'MARITAL STATUS', 'EDUCATIONAL STATUS',
        'SPECIAL NEED', 'ENROLLED AT', 'FACILITY NAME', 'FACILITY LGA', 'FACILITY WARD',
        'DATE OF ENROLLMENT',
    ];

    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        return Enrollee::query()
            ->select([
                'enrollees.id', 'enrollees.enrollee_id', 'enrollees.legacy_enrollee_id',
                'enrollees.first_name', 'enrollees.middle_name', 'enrollees.last_name',
                'enrollees.date_of_birth', 'enrollees.sex', 'enrollees.address',
                'enrollees.village', 'enrollees.community', 'enrollees.nin', 'enrollees.phone',
                'enrollees.disability', 'enrollees.enrollment_date',
                'enrollees.lga_id', 'enrollees.ward_id', 'enrollees.facility_id',
            ])
            ->where('enrollees.status', Enrollee::STATUS_ACTIVE)
            ->whereHas('fundingType', fn (Builder $query) => $query
                ->whereIn('name', ['BHCPF', 'Basic Healthcare Provision Fund']))
            // Migrated HUWE records and current vulnerable programme enrollments.
            ->where(function (Builder $query): void {
                $query->whereHas('insuranceProgramme', fn (Builder $programme) => $programme->where('code', 'vulnerable_groups'))
                    ->orWhereNull('enrollees.insurance_programme_id');
            })
            ->when($this->filters['from_date'] ?? null, fn (Builder $query, $date) => $query->where('enrollees.enrollment_date', '>=', $date.' 00:00:00'))
            ->when($this->filters['to_date'] ?? null, fn (Builder $query, $date) => $query->where('enrollees.enrollment_date', '<=', $date.' 23:59:59'))
            ->orderByDesc('enrollees.enrollment_date')->orderByDesc('enrollees.id');
    }

    public function map(stdClass $enrollee, int $serial): array
    {
        return [
            $serial, 'Niger', $enrollee->legacy_enrollee_id ?: $enrollee->enrollee_id, 'BHCPF',
            $enrollee->first_name, $enrollee->middle_name, $enrollee->last_name,
            $this->formatDate($enrollee->date_of_birth),
            match ((int) $enrollee->sex) {
                1 => 'MALE', 2 => 'FEMALE', default => ''
            },
            $enrollee->address, $enrollee->lga_name, $enrollee->ward_name,
            $enrollee->village ?: $enrollee->community,
            '', $enrollee->nin, $enrollee->phone, '', '', '', $enrollee->disability,
            $enrollee->facility_name, $enrollee->facility_name,
            $enrollee->facility_lga_name, $enrollee->facility_ward_name,
            $this->formatDate($enrollee->enrollment_date),
        ];
    }

    public function download(): BinaryFileResponse
    {
        // This request can include the entire BHCPF population. Do not change PHP's global limit.
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }

        // A zero memory allowance writes to a private temporary file, removed automatically.
        $file = new SplTempFileObject(0);
        // Apply Eloquent scopes before reading plain rows, avoiding model hydration/casts per cell.
        $query = $this->query()->toBase();
        // Sort only the small ID list once, instead of repeatedly sorting all report fields.
        $ids = (clone $query)->select('enrollees.id')->pluck('enrollees.id');
        // The legacy report numbers oldest first, then displays newest first.
        $serial = $ids->count();
        $this->write($file, "\xEF\xBB\xBF".view('reports.bhcpf-mande-header', ['headings' => self::HEADINGS])->render());
        foreach ($this->rows($query, $ids) as $enrollee) {
            $html = '<tr>';
            foreach ($this->map($enrollee, $serial--) as $column => $value) {
                $class = in_array($column, [2, 14, 15], true) ? ' class="text"' : '';
                $html .= '<td'.$class.'>'.e((string) ($value ?? '')).'</td>';
            }
            $this->write($file, $html.'</tr>');
        }
        $this->write($file, '</table></body></html>');
        if (! $file->fflush()) {
            throw new RuntimeException('Unable to finish writing the BHCPF report file.');
        }

        // Return the completed file only. Generation errors remain normal API errors.
        return (new BinaryFileResponse($file, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ], false))->setContentDisposition('attachment', 'BHCPF_Enrollees_'.now()->format('Y-m-d').'.xls');
    }

    /** @return \Generator<int, stdClass> */
    private function rows(QueryBuilder $query, \Illuminate\Support\Collection $ids): \Generator
    {
        foreach ($ids->chunk(self::BATCH_SIZE) as $batchIds) {
            // Model-derived subqueries retain location scopes, including facility soft deletes.
            $rows = (clone $query)->reorder()->whereIn('enrollees.id', $batchIds->all())
                ->addSelect([
                    'report_lga.name as lga_name', 'report_ward.name as ward_name',
                    'report_facility.name as facility_name',
                    'report_facility_lga.name as facility_lga_name',
                    'report_facility_ward.name as facility_ward_name',
                ])
                ->leftJoinSub(Lga::query()->select('id', 'name')->toBase(), 'report_lga', 'report_lga.id', '=', 'enrollees.lga_id')
                ->leftJoinSub(Ward::query()->select('id', 'name')->toBase(), 'report_ward', 'report_ward.id', '=', 'enrollees.ward_id')
                ->leftJoinSub(Facility::query()->select('id', 'name', 'lga_id', 'ward_id')->toBase(), 'report_facility', 'report_facility.id', '=', 'enrollees.facility_id')
                ->leftJoinSub(Lga::query()->select('id', 'name')->toBase(), 'report_facility_lga', 'report_facility_lga.id', '=', 'report_facility.lga_id')
                ->leftJoinSub(Ward::query()->select('id', 'name')->toBase(), 'report_facility_ward', 'report_facility_ward.id', '=', 'report_facility.ward_id')
                ->get()->keyBy('id');

            // Reuse the captured order without another SQL sort of wide report rows.
            foreach ($batchIds as $id) {
                if (isset($rows[$id])) {
                    yield $rows[$id];
                }
            }
        }
    }

    private function formatDate(?string $date): string
    {
        // DATE/DATETIME columns are returned in database ISO format, without per-row Carbon casts.
        return $date && substr($date, 0, 10) !== '0000-00-00'
            ? substr($date, 8, 2).'/'.substr($date, 5, 2).'/'.substr($date, 0, 4)
            : '';
    }

    private function write(SplTempFileObject $file, string $contents): void
    {
        $length = strlen($contents);
        $written = 0;
        while ($written < $length) {
            $bytes = $file->fwrite(substr($contents, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Unable to write the BHCPF report file. Check available disk space.');
            }
            $written += $bytes;
        }
    }
}
