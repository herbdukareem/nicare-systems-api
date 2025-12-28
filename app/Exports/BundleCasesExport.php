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

class BundleCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'Normal Delivery Bundle',
                    'nicare_code' => 'NGSCHA/BUNDLE/S/0001',
                    'service_description' => 'Normal Delivery - Complete Package',
                    'level_of_care' => 'Secondary',
                    'group' => 'OBSTETRICS & GYNAECOLOGY',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'is_bundle' => 'Yes',
                    'bundle_price' => 50000.00,
                    'diagnosis_icd10' => 'O80',
                ],
                (object) [
                    'case_name' => 'Caesarean Section Bundle',
                    'nicare_code' => 'NGSCHA/BUNDLE/S/0002',
                    'service_description' => 'Caesarean Section - Complete Package',
                    'level_of_care' => 'Secondary',
                    'group' => 'OBSTETRICS & GYNAECOLOGY',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'is_bundle' => 'Yes',
                    'bundle_price' => 120000.00,
                    'diagnosis_icd10' => 'O82',
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
            'Group *',
            'PA Required',
            'Referable',
            'Is Bundle',
            'Price (₦) *',
            'ICD-10 Code *',
        ];
    }

    public function map($case): array
    {
        return [
            $case->case_name ?? '',
            $case->nicare_code ?? '',
            $case->service_description ?? '',
            $case->level_of_care ?? 'Secondary',
            $case->group ?? '',
            $case->pa_required ?? 'Yes',
            $case->referable ?? 'Yes',
            $case->is_bundle ?? 'Yes',
            $case->bundle_price ?? '',
            $case->diagnosis_icd10 ?? '',
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

