<?php

namespace App\Exports;

use App\Models\CaseRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;
    protected bool $isTemplate;

    public function __construct(array $filters = [], bool $isTemplate = false)
    {
        $this->filters = $filters;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Return collection of cases or template data
     */
    public function collection()
    {
        if ($this->isTemplate) {
            // Return sample data for template with only import fields
            return collect([
                (object) [
                    'nicare_code' => 'NGSCHS/GCons/P/0001',
                    'case_description' => 'General Consultation - Primary Level',
                    'level_of_care' => 'Primary',
                    'price' => 1000.00,
                    'group' => 'GENERAL CONSULTATION',
                    'pa_required' => 'No',
                    'referable' => 'Yes'
                ],
                (object) [
                    'nicare_code' => 'NGSCHS/Paed/S/0001',
                    'case_description' => 'Paediatric Consultation - Secondary Level',
                    'level_of_care' => 'Secondary',
                    'price' => 2000.00,
                    'group' => 'PAEDIATRICS',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes'
                ],
                (object) [
                    'nicare_code' => 'NGSCHS/IM/T/0001',
                    'case_description' => 'Internal Medicine - Tertiary Level',
                    'level_of_care' => 'Tertiary',
                    'price' => 5000.00,
                    'group' => 'INTERNAL MEDICINE (PRV)',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes'
                ]
            ]);
        }

        $query = CaseRecord::with(['creator:id,name']);

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

        return $query->orderBy('case_description')->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        if ($this->isTemplate) {
            return [
                'NiCare Code *',
                'Case Description *',
                'Level of Care *',
                'Price (₦) *',
                'Group *',
                'PA Required',
                'Referable'
            ];
        }

        return [
            'NiCare Code',
            'Case Description',
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
    public function map($case): array
    {
        if ($this->isTemplate) {
            // For template, return as-is since we already have Yes/No strings
            return [
                $case->nicare_code,
                $case->case_description,
                $case->level_of_care,
                $case->price,
                $case->group,
                $case->pa_required,  // Already 'Yes' or 'No'
                $case->referable     // Already 'Yes' or 'No'
            ];
        }

        return [
            $case->nicare_code,
            $case->case_description,
            $case->level_of_care,
            number_format($case->price, 2),
            $case->group,
            $case->pa_required ? 'Yes' : 'No',
            $case->referable ? 'Yes' : 'No',
            $case->status ? 'Active' : 'Inactive',
            $case->created_at->format('Y-m-d H:i:s'),
            $case->creator->name ?? 'N/A'
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row as bold with background color
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center']
            ],
        ];
    }
}

