<?php

namespace App\Exports;

use App\Models\CaseRecord;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class LaboratoryCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $cases;
    protected $isTemplate;

    public function __construct($cases = [], $isTemplate = false)
    {
        $this->cases = $cases;
        $this->isTemplate = $isTemplate;
    }

    public function collection()
    {
        if ($this->isTemplate) {
            return collect([
                (object) [
                    'case_name' => 'Full Blood Count (FBC)',
                    'service_description' => 'Complete Blood Count with Differential',
                    'level_of_care' => 'Primary',
                    'price' => 2500.00,
                    'group' => 'LABORATORY',
                    'pa_required' => 'No',
                    'referable' => 'Yes',
                    'test_name' => 'Full Blood Count',
                    'test_code' => 'FBC001',
                    'specimen_type' => 'Whole Blood',
                    'test_category' => 'Hematology',
                    'turnaround_time' => 24,
                    'fasting_required' => 'No',
                ],
                (object) [
                    'case_name' => 'Fasting Blood Sugar',
                    'nicare_code' => 'NGSCHA/LAB/P/0002',
                    'service_description' => 'Fasting Blood Glucose Test',
                    'level_of_care' => 'Primary',
                    'price' => 1500.00,
                    'group' => 'LABORATORY',
                    'pa_required' => 'No',
                    'referable' => 'Yes',
                    'test_name' => 'Fasting Blood Sugar',
                    'test_code' => 'FBS001',
                    'specimen_type' => 'Serum',
                    'test_category' => 'Chemistry',
                    'turnaround_time' => 12,
                    'fasting_required' => 'Yes',
                ],
            ]);
        }

        return collect($this->cases);
    }

    public function headings(): array
    {
        return [
            'Case Name *',
            'NiCare Code *',
            'Service Description *',
            'Level of Care *',
            'Price (₦) *',
            'Group *',
            'PA Required',
            'Referable',
            'Test Name *',
            'Test Code',
            'Specimen Type',
            'Test Category',
            'Turnaround Time (hours)',
            'Fasting Required',
        ];
    }

    public function map($case): array
    {
        return [
            $case->case_name ?? '',
            $case->nicare_code ?? '',
            $case->service_description ?? '',
            $case->level_of_care ?? 'Primary',
            $case->price ?? '',
            $case->group ?? 'LABORATORY',
            $case->pa_required ?? 'No',
            $case->referable ?? 'Yes',
            $case->test_name ?? '',
            $case->test_code ?? '',
            $case->specimen_type ?? '',
            $case->test_category ?? '',
            $case->turnaround_time ?? '',
            $case->fasting_required ?? 'No',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Add dropdowns for Level of Care (column C)
                for ($row = 2; $row <= $highestRow + 100; $row++) {
                    $validation = $sheet->getCell("C{$row}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setFormula1('"Primary,Secondary,Tertiary"');
                    $validation->setShowDropDown(true);
                }
            },
        ];
    }
}

