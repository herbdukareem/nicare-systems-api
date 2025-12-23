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

class DrugCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
            // Return sample data for template
            return collect([
                (object) [
                    'case_name' => 'Paracetamol 500mg Tablets',
                    'nicare_code' => 'NGSCHA/DRUG/P/0001',
                    'service_description' => 'Paracetamol 500mg Tablets - Pack of 20',
                    'level_of_care' => 'Primary',
                    'price' => 500.00,
                    'group' => 'DRUGS',
                    'pa_required' => 'No',
                    'referable' => 'No',
                    'generic_name' => 'Paracetamol',
                    'brand_name' => 'Panadol',
                    'dosage_form' => 'Tablet',
                    'strength' => '500mg',
                    'pack_description' => 'Pack of 20 tablets',
                    'route_of_administration' => 'Oral',
                    'manufacturer' => 'GSK',
                    'drug_class' => 'Analgesic',
                    'nafdac_number' => 'A4-1234',
                ],
                (object) [
                    'case_name' => 'Amoxicillin 250mg Capsules',
                    'nicare_code' => 'NGSCHA/DRUG/P/0002',
                    'service_description' => 'Amoxicillin 250mg Capsules - Pack of 21',
                    'level_of_care' => 'Primary',
                    'price' => 1200.00,
                    'group' => 'DRUGS',
                    'pa_required' => 'No',
                    'referable' => 'No',
                    'generic_name' => 'Amoxicillin',
                    'brand_name' => 'Amoxil',
                    'dosage_form' => 'Capsule',
                    'strength' => '250mg',
                    'pack_description' => 'Pack of 21 capsules',
                    'route_of_administration' => 'Oral',
                    'manufacturer' => 'GSK',
                    'drug_class' => 'Antibiotic',
                    'nafdac_number' => 'A4-5678',
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
            'Generic Name *',
            'Brand Name',
            'Dosage Form',
            'Strength',
            'Pack Description',
            'Route of Administration',
            'Manufacturer',
            'Drug Class',
            'NAFDAC Number',
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
            $case->group ?? 'DRUGS',
            $case->pa_required ?? 'No',
            $case->referable ?? 'No',
            $case->generic_name ?? '',
            $case->brand_name ?? '',
            $case->dosage_form ?? '',
            $case->strength ?? '',
            $case->pack_description ?? '',
            $case->route_of_administration ?? '',
            $case->manufacturer ?? '',
            $case->drug_class ?? '',
            $case->nafdac_number ?? '',
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
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Invalid Level of Care');
                    $validation->setError('Please select from the dropdown list.');
                    $validation->setPromptTitle('Level of Care');
                    $validation->setPrompt('Select: Primary, Secondary, or Tertiary');
                    $validation->setFormula1('"Primary,Secondary,Tertiary"');
                }
            },
        ];
    }
}

