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

class RadiologyCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'Chest X-Ray',
                    'nicare_code' => 'NGSCHA/RAD/P/0001',
                    'service_description' => 'Chest X-Ray - PA View',
                    'level_of_care' => 'Secondary',
                    'price' => 5000.00,
                    'group' => 'RADIOLOGY',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'examination_name' => 'Chest X-Ray',
                    'examination_code' => 'XR001',
                    'modality' => 'X-Ray',
                    'body_part' => 'Chest',
                    'contrast_required' => 'No',
                    'pregnancy_safe' => 'No',
                ],
                (object) [
                    'case_name' => 'Abdominal Ultrasound',
                    'nicare_code' => 'NGSCHA/RAD/S/0001',
                    'service_description' => 'Abdominal Ultrasound Scan',
                    'level_of_care' => 'Secondary',
                    'price' => 8000.00,
                    'group' => 'RADIOLOGY',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'examination_name' => 'Abdominal Ultrasound',
                    'examination_code' => 'US001',
                    'modality' => 'Ultrasound',
                    'body_part' => 'Abdomen',
                    'contrast_required' => 'No',
                    'pregnancy_safe' => 'Yes',
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
            'Examination Name *',
            'Examination Code',
            'Modality',
            'Body Part',
            'Contrast Required',
            'Pregnancy Safe',
        ];
    }

    public function map($case): array
    {
        return [
            $case->case_name ?? '',
            $case->nicare_code ?? '',
            $case->service_description ?? '',
            $case->level_of_care ?? 'Secondary',
            $case->price ?? '',
            $case->group ?? 'RADIOLOGY',
            $case->pa_required ?? 'Yes',
            $case->referable ?? 'Yes',
            $case->examination_name ?? '',
            $case->examination_code ?? '',
            $case->modality ?? '',
            $case->body_part ?? '',
            $case->contrast_required ?? 'No',
            $case->pregnancy_safe ?? 'No',
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

