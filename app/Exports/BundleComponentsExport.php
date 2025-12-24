<?php

namespace App\Exports;

use App\Models\BundleComponent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BundleComponentsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $components;
    protected $isTemplate;

    public function __construct($components = [], $isTemplate = false)
    {
        $this->components = $components;
        $this->isTemplate = $isTemplate;
    }

    public function collection()
    {
        if ($this->isTemplate) {
            // Return sample data for template
            return collect([
                (object) [
                    'bundle_nicare_code' => 'NGSCHA/BUNDLE/S/0001',
                    'bundle_name' => 'Normal Delivery Bundle',
                    'component_nicare_code' => 'NGSCHA/DRUG/P/0001',
                    'component_name' => 'Paracetamol 500mg Tablets',
                    'max_quantity' => 10,
                    'item_type' => 'DRUG',
                ],
                (object) [
                    'bundle_nicare_code' => 'NGSCHA/BUNDLE/S/0001',
                    'bundle_name' => 'Normal Delivery Bundle',
                    'component_nicare_code' => 'NGSCHA/LAB/P/0001',
                    'component_name' => 'Full Blood Count (FBC)',
                    'max_quantity' => 1,
                    'item_type' => 'LABORATORY',
                ],
                (object) [
                    'bundle_nicare_code' => 'NGSCHA/BUNDLE/S/0002',
                    'bundle_name' => 'Caesarean Section Bundle',
                    'component_nicare_code' => 'NGSCHA/DRUG/P/0002',
                    'component_name' => 'Amoxicillin 250mg Capsules',
                    'max_quantity' => 21,
                    'item_type' => 'DRUG',
                ],
                (object) [
                    'bundle_nicare_code' => 'NGSCHA/BUNDLE/S/0002',
                    'bundle_name' => 'Caesarean Section Bundle',
                    'component_nicare_code' => 'NGSCHA/LAB/P/0002',
                    'component_name' => 'Fasting Blood Sugar',
                    'max_quantity' => 1,
                    'item_type' => 'LABORATORY',
                ],
            ]);
        }

        return collect($this->components);
    }

    public function headings(): array
    {
        return [
            'Bundle NiCare Code *',
            'Bundle Name',
            'Component NiCare Code *',
            'Component Name',
            'Max Quantity *',
            'Item Type *',
        ];
    }

    public function map($component): array
    {
        return [
            $component->bundle_nicare_code ?? '',
            $component->bundle_name ?? '',
            $component->component_nicare_code ?? '',
            $component->component_name ?? '',
            $component->max_quantity ?? 1,
            $component->item_type ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E9']
                ]
            ],
        ];
    }
}

