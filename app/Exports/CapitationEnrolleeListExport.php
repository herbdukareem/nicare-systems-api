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
            $row->date_of_birth?->format('Y-m-d'),
            $row->lga_name,
            $row->ward_name,
            $row->coverage_start_date?->format('Y-m-d'),
            $row->coverage_end_date?->format('Y-m-d'),
            $row->capitation_start_date?->format('Y-m-d'),
            strtoupper((string) $row->duplicate_nin_policy),
            $row->captured_at?->format('Y-m-d H:i:s'),
        ];
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
