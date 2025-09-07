<?php

namespace App\Exports;

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
        $query = Enrollee::with(['enrolleeType', 'facility', 'lga', 'ward', 'village']);

        // Apply the same filters as in the index method
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
            'First Name',
            'Middle Name',
            'Last Name',
            'Phone',
            'Email',
            'NIN',
            'Date of Birth',
            'Age',
            'Gender',
            'Marital Status',
            'Address',
            'Enrollee Type',
            'Facility',
            'LGA',
            'Ward',
            'Village',
            'Premium ID',
            'Employment Detail ID',
            'Funding Type',
            'Benefactor',
            'Capitation Start Date',
            'Approval Date',
            'Status',
            'Created At',
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
            $enrollee->first_name,
            $enrollee->middle_name,
            $enrollee->last_name,
            $enrollee->phone,
            $enrollee->email,
            $enrollee->nin,
            $enrollee->date_of_birth ? $enrollee->date_of_birth->format('Y-m-d') : '',
            $enrollee->date_of_birth ? $enrollee->date_of_birth->age : '',
            $enrollee->gender,
            $enrollee->marital_status,
            $enrollee->address,
            $enrollee->enrolleeType ? $enrollee->enrolleeType->name : '',
            $enrollee->facility ? $enrollee->facility->name : '',
            $enrollee->lga ? $enrollee->lga->name : '',
            $enrollee->ward ? $enrollee->ward->name : '',
            $enrollee->village,
            $enrollee->premium_id,
            $enrollee->employment_detail_id,
            $enrollee->fundingType ? $enrollee->fundingType->name : '',
            $enrollee->benefactor ? $enrollee->benefactor->name : '',
            $enrollee->capitation_start_date ? $enrollee->capitation_start_date->format('Y-m-d') : '',
            $enrollee->approval_date ? $enrollee->approval_date->format('Y-m-d H:i:s') : '',
            $enrollee->status ? $enrollee->status->value : '',
            $enrollee->created_at ? $enrollee->created_at->format('Y-m-d H:i:s') : '',
        ];
    }

    /**
     * Apply filters to the query
     */
    private function applyFilters($query)
    {
        $request = $this->filters;

        // Apply filters with array support
        if ($request->has('status')) {
            $status = $request->status;
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('lga_id')) {
            $lgaIds = $request->lga_id;
            if (is_array($lgaIds)) {
                $query->whereIn('lga_id', $lgaIds);
            } else {
                $query->where('lga_id', $lgaIds);
            }
        }

        if ($request->has('ward_id')) {
            $wardIds = $request->ward_id;
            if (is_array($wardIds)) {
                $query->whereIn('ward_id', $wardIds);
            } else {
                $query->where('ward_id', $wardIds);
            }
        }

        if ($request->has('facility_id')) {
            $facilityIds = $request->facility_id;
            if (is_array($facilityIds)) {
                $query->whereIn('facility_id', $facilityIds);
            } else {
                $query->where('facility_id', $facilityIds);
            }
        }

        if ($request->has('enrollee_type_id')) {
            $enrolleeTypeIds = $request->enrollee_type_id;
            if (is_array($enrolleeTypeIds)) {
                $query->whereIn('enrollee_type_id', $enrolleeTypeIds);
            } else {
                $query->where('enrollee_type_id', $enrolleeTypeIds);
            }
        }

        if ($request->has('gender')) {
            $genders = $request->gender;
            if (is_array($genders)) {
                $query->whereIn('gender', $genders);
            } else {
                $query->where('gender', $genders);
            }
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('approval_date_from')) {
            $query->whereDate('approval_date', '>=', $request->approval_date_from);
        }

        if ($request->has('approval_date_to')) {
            $query->whereDate('approval_date', '<=', $request->approval_date_to);
        }

        // Age range filter
        if ($request->has('age_from') || $request->has('age_to')) {
            $query->where(function($q) use ($request) {
                if ($request->has('age_from')) {
                    $dateFrom = now()->subYears($request->age_from)->format('Y-m-d');
                    $q->where('date_of_birth', '<=', $dateFrom);
                }
                if ($request->has('age_to')) {
                    $dateTo = now()->subYears($request->age_to)->format('Y-m-d');
                    $q->where('date_of_birth', '>=', $dateTo);
                }
            });
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('enrollee_id', 'like', "%{$search}%")
                  ->orWhere('nin', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
    }
}
