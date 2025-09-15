<?php

namespace App\Exports;

use App\Models\Drug;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DrugsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;
    protected bool $isTemplate;

    public function __construct(array $filters = [], bool $isTemplate = false)
    {
        $this->filters = $filters;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Return collection of drugs or template data
     */
    public function collection()
    {
        if ($this->isTemplate) {
            // Return sample data for template
            return collect([
                (object) [
                    'nicare_code' => 'NGSCHA/DRUG/001',
                    'drug_name' => 'Paracetamol',
                    'drug_dosage_form' => 'Tablet',
                    'drug_strength' => '500mg',
                    'drug_presentation' => 'Tab',
                    'drug_unit_price' => 50.00,
                    'status' => true,
                    'created_at' => now(),
                    'creator' => (object) ['name' => 'System']
                ],
                (object) [
                    'nicare_code' => 'NGSCHA/DRUG/002',
                    'drug_name' => 'Amoxicillin',
                    'drug_dosage_form' => 'Capsule',
                    'drug_strength' => '250mg',
                    'drug_presentation' => 'Cap',
                    'drug_unit_price' => 75.00,
                    'status' => true,
                    'created_at' => now(),
                    'creator' => (object) ['name' => 'System']
                ]
            ]);
        }

        $query = Drug::with(['creator:id,name']);

        // Apply filters
        if (!empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== '') {
            $query->where('status', (bool) $this->filters['status']);
        }

        return $query->orderBy('drug_name')->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        if ($this->isTemplate) {
            return [
                'NiCare_Code',
                'drug_Name',
                'drug_Dosage_Form',
                'drug_Strength',
                'drug_Presentation',
                'drug_Unit_Price'
            ];
        }

        return [
            'NiCare Code',
            'Drug Name',
            'Dosage Form',
            'Strength',
            'Presentation',
            'Unit Price (₦)',
            'Status',
            'Created Date',
            'Created By'
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($drug): array
    {
        if ($this->isTemplate) {
            return [
                $drug->nicare_code,
                $drug->drug_name,
                $drug->drug_dosage_form,
                $drug->drug_strength,
                $drug->drug_presentation,
                $drug->drug_unit_price
            ];
        }

        return [
            $drug->nicare_code,
            $drug->drug_name,
            $drug->drug_dosage_form,
            $drug->drug_strength ?? '',
            $drug->drug_presentation,
            number_format($drug->drug_unit_price, 2),
            $drug->status ? 'Active' : 'Inactive',
            $drug->created_at->format('Y-m-d H:i:s'),
            $drug->creator->name ?? 'N/A'
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
