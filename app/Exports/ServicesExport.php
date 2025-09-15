<?php

namespace App\Exports;

use App\Models\Service;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ServicesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;
    protected bool $isTemplate;

    public function __construct(array $filters = [], bool $isTemplate = false)
    {
        $this->filters = $filters;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Return collection of services or template data
     */
    public function collection()
    {
        if ($this->isTemplate) {
            // Return sample data for template
            return collect([
                (object) [
                    'nicare_code' => 'NGSCHS/GCons/P/0001',
                    'service_description' => 'General Consultation',
                    'level_of_care' => 'Primary',
                    'price' => 1000.00,
                    'group' => 'GENERAL CONSULTATION',
                    'pa_required' => false,
                    'referable' => true,
                    'status' => true,
                    'created_at' => now(),
                    'creator' => (object) ['name' => 'System']
                ],
                (object) [
                    'nicare_code' => 'NGSCHS/Paed/S/0001',
                    'service_description' => 'Paediatric Consultation',
                    'level_of_care' => 'Secondary',
                    'price' => 2000.00,
                    'group' => 'PAEDIATRICS',
                    'pa_required' => true,
                    'referable' => true,
                    'status' => true,
                    'created_at' => now(),
                    'creator' => (object) ['name' => 'System']
                ]
            ]);
        }

        $query = Service::with(['creator:id,name']);

        // Apply filters
        if (!empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== '') {
            $query->where('status', (bool) $this->filters['status']);
        }

        if (!empty($this->filters['level_of_care'])) {
            $query->byLevelOfCare($this->filters['level_of_care']);
        }

        if (!empty($this->filters['group'])) {
            $query->byGroup($this->filters['group']);
        }

        return $query->orderBy('service_description')->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        if ($this->isTemplate) {
            return [
                'nicare_code',
                'service_description',
                'level_of_care',
                'price',
                'group',
                'pa_required',
                'referable'
            ];
        }

        return [
            'NiCare Code',
            'Service Description',
            'Level of Care',
            'Price (₦)',
            'Group',
            'PA Required',
            'Referable',
            'Status',
            'Created Date',
            'Created By'
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($service): array
    {
        if ($this->isTemplate) {
            return [
                $service->nicare_code,
                $service->service_description,
                $service->level_of_care,
                $service->price,
                $service->group,
                $service->pa_required ? 'Yes' : 'No',
                $service->referable ? 'Yes' : 'No'
            ];
        }

        return [
            $service->nicare_code,
            $service->service_description,
            $service->level_of_care,
            number_format($service->price, 2),
            $service->group,
            $service->pa_required ? 'Yes' : 'No',
            $service->referable ? 'Yes' : 'No',
            $service->status ? 'Active' : 'Inactive',
            $service->created_at->format('Y-m-d H:i:s'),
            $service->creator->name ?? 'N/A'
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold
            1 => ['font' => ['bold' => true]],
        ];
    }
}
