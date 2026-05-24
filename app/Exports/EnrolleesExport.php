<?php

namespace App\Exports;

use App\Filters\EnrolleeFilter;
use App\Models\Enrollee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Http\Request;

class EnrolleesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $filters;

    public function __construct(Request $request)
    {
        $this->filters = $request;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Enrollee::with([
            'facility', 'lga', 'ward', 'fundingType', 'benefactor',
            'enrollmentPhase',
        ]);

        $this->applyFilters($query);

        return $query->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Enrollee ID',
            'Legacy ID',
            'Full Name',
            'NIN',
            'Phone',
            'Email',
            'Gender',
            'Date of Birth',
            'LGA',
            'Ward',
            'Facility',
            'Funding Type',
            'Benefactor',
            'Enrollment Phase',
            'Status',
            'Coverage Status',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * @param Enrollee $enrollee
     * @return array
     */
    public function map($enrollee): array
    {
        return [
            $enrollee->enrollee_id,
            $enrollee->legacy_id,
            $enrollee->full_name,
            $enrollee->nin,
            $enrollee->phone,
            $enrollee->email,
            $enrollee->sex == 1 ? 'Male' : ($enrollee->sex == 2 ? 'Female' : 'Other'),
            $enrollee->date_of_birth ? $enrollee->date_of_birth->format('Y-m-d') : '',
            $enrollee->lga ? $enrollee->lga->name : '',
            $enrollee->ward ? $enrollee->ward->name : '',
            $enrollee->facility ? $enrollee->facility->name : '',
            $enrollee->fundingType ? $enrollee->fundingType->name : '',
            $enrollee->benefactor ? $enrollee->benefactor->name : '',
            $enrollee->enrollmentPhase ? $enrollee->enrollmentPhase->name : '',
            $this->statusLabel((int) $enrollee->status),
            $this->coverageStatus($enrollee),
            $enrollee->created_at ? $enrollee->created_at->format('Y-m-d H:i:s') : '',
            $enrollee->updated_at ? $enrollee->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query)
    {
        EnrolleeFilter::apply($query, $this->filters->all());
        $query->orderBy('created_at', 'desc');
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            Enrollee::STATUS_PENDING => 'Pending Approval',
            Enrollee::STATUS_ACTIVE => 'Approved',
            Enrollee::STATUS_REJECTED => 'Rejected',
            Enrollee::STATUS_SUSPENDED => 'Suspended',
            Enrollee::STATUS_EXPIRED => 'Inactive',
            default => 'Unknown',
        };
    }

    private function coverageStatus(Enrollee $enrollee): string
    {
        if (!$enrollee->coverage_start_date) {
            return 'Pending';
        }

        if ($enrollee->hasValidCoverage()) {
            return 'Active';
        }

        if ($enrollee->coverage_start_date->isFuture()) {
            return 'Future';
        }

        return 'Expired';
    }
}
