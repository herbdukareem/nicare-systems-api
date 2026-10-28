<?php

namespace App\Exports;

use App\Models\Capitation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CapitationEnrolleeListExport implements FromQuery, WithHeadings, WithMapping, WithTitle, WithColumnWidths, WithCustomChunkSize
{
    public function __construct(
        private readonly Capitation $capitation,
        private readonly EloquentBuilder $query,
    ) {
    }

    public function query(): EloquentBuilder
    {
        return $this->query;
    }

    public static function columns(): array
    {
        return [
            'id', 'funding_type_name', 'facility_name', 'facility_code',
            'enrollee_number', 'legacy_id', 'full_name', 'nin', 'phone', 'gender',
            'date_of_birth', 'lga_name', 'ward_name', 'coverage_start_date',
            'coverage_end_date', 'capitation_start_date', 'duplicate_nin_policy', 'captured_at',
        ];
    }

    public function headings(): array
    {
        return [
            'Capitation Period',
            'Funding Type',
            'Facility',
            'Facility Code',
            'Enrollee ID',
            'Legacy ID',
            'Full Name',
            'NIN',
            'Phone',
            'Gender',
            'Date of Birth',
            'LGA',
            'Ward',
            'Coverage Start',
            'Coverage End',
            'Capitation Start',
            'Duplicate NIN Policy',
            'Captured At',
        ];
    }

    public function map($row): array
    {
        return [
            $this->capitation->name,
            $row->funding_type_name,
            $row->facility_name,
            $row->facility_code,
            $row->enrollee_number,
            $row->legacy_id,
            $row->full_name,
            $row->nin,
            $row->phone,
            $row->gender,
            $this->formatDate($row->date_of_birth),
            $row->lga_name,
            $row->ward_name,
            $this->formatDate($row->coverage_start_date),
            $this->formatDate($row->coverage_end_date),
            $this->formatDate($row->capitation_start_date),
            strtoupper((string) $row->duplicate_nin_policy),
            $this->formatDate($row->captured_at, true),
        ];
    }

    private function formatDate(mixed $value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format($withTime ? 'Y-m-d H:i:s' : 'Y-m-d')
            : substr((string) $value, 0, $withTime ? 19 : 10);
    }

    public function title(): string
    {
        return 'Enrollee Snapshot List';
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 24,
            'C' => 36,
            'D' => 18,
            'E' => 18,
            'F' => 14,
            'G' => 32,
            'H' => 18,
            'I' => 18,
            'J' => 12,
            'K' => 16,
            'L' => 18,
            'M' => 18,
            'N' => 16,
            'O' => 16,
            'P' => 16,
            'Q' => 18,
            'R' => 20,
        ];
    }
}
