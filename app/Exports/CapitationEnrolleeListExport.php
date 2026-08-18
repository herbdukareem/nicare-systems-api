<?php

namespace App\Exports;

use App\Models\Capitation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CapitationEnrolleeListExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Capitation $capitation,
        private readonly Collection $rows,
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
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
}
