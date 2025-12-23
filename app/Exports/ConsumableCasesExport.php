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

class ConsumableCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'Surgical Gloves - Sterile',
                    'nicare_code' => 'NGSCHA/CONSUM/P/0001',
                    'service_description' => 'Sterile Surgical Gloves - Size 7.5',
                    'level_of_care' => 'Primary',
                    'price' => 500.00,
                    'group' => 'CONSUMABLES',
                    'pa_required' => 'No',
                    'referable' => 'No',
                    'item_name' => 'Surgical Gloves',
                    'item_code' => 'CONS001',
                    'category' => 'Gloves',
                    'unit_of_measure' => 'Pair',
                    'units_per_pack' => 50,
                    'sterile' => 'Yes',
                ],
                (object) [
                    'case_name' => 'Gauze Swabs',
                    'nicare_code' => 'NGSCHA/CONSUM/P/0002',
                    'service_description' => 'Sterile Gauze Swabs - 10cm x 10cm',
                    'level_of_care' => 'Primary',
                    'price' => 200.00,
                    'group' => 'CONSUMABLES',
                    'pa_required' => 'No',
                    'referable' => 'No',
                    'item_name' => 'Gauze Swabs',
                    'item_code' => 'CONS002',
                    'category' => 'Dressings',
                    'unit_of_measure' => 'Pack',
                    'units_per_pack' => 100,
                    'sterile' => 'Yes',
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
            'Item Name *',
            'Item Code',
            'Category',
            'Unit of Measure',
            'Units Per Pack',
            'Sterile',
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
            $case->group ?? 'CONSUMABLES',
            $case->pa_required ?? 'No',
            $case->referable ?? 'No',
            $case->item_name ?? '',
            $case->item_code ?? '',
            $case->category ?? '',
            $case->unit_of_measure ?? '',
            $case->units_per_pack ?? '',
            $case->sterile ?? 'No',
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

