<?php

namespace App\Exports;

use App\Models\CaseRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'General Consultation',
                    'service_description' => 'General Consultation - Primary Level',
                    'level_of_care' => 'Primary',
                    'price' => 1000.00,
                    'group' => 'GENERAL CONSULTATION',
                    'pa_required' => 'No',
                    'referable' => 'Yes',
                    'is_bundle' => 'No',
                    'bundle_price' => '',
                    'diagnosis_icd10' => ''
                ],
                (object) [
                    'case_name' => 'Paediatric Consultation',
                    'service_description' => 'Paediatric Consultation - Secondary Level',
                    'level_of_care' => 'Secondary',
                    'price' => 2000.00,
                    'group' => 'PAEDIATRICS',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'is_bundle' => 'No',
                    'bundle_price' => '',
                    'diagnosis_icd10' => ''
                ],
                (object) [
                    'case_name' => 'Normal Delivery Bundle',
                    'service_description' => 'Normal Delivery - Complete Package',
                    'level_of_care' => 'Secondary',
                    'price' => 0.00,
                    'group' => 'OBSTETRICS & GYNAECOLOGY',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'is_bundle' => 'Yes',
                    'bundle_price' => 50000.00,
                    'diagnosis_icd10' => 'O80'
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
                'Case Name *',
                'Service Description *',
                'Level of Care *',
                'Price (₦) *',
                'Group *',
                'PA Required',
                'Referable',
                'Is Bundle',
                'Bundle Price (₦)',
                'ICD-10 Code'
            ];
        }

        return [
            'Case Name',
            'NiCare Code',
            'Service Description',
            'Level of Care',
            'Price (₦)',
            'Group',
            'PA Required',
            'Referable',
            'Is Bundle',
            'Bundle Price (₦)',
            'ICD-10 Code',
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
                $case->case_name,
                $case->service_description,
                $case->level_of_care,
                $case->price,
                $case->group,
                $case->pa_required,  // Already 'Yes' or 'No'
                $case->referable,    // Already 'Yes' or 'No'
                $case->is_bundle,    // Already 'Yes' or 'No'
                $case->bundle_price, // Empty string or number
                $case->diagnosis_icd10 // Empty string or ICD-10 code
            ];
        }

        return [
            $case->case_name ?? '',
            $case->nicare_code,
            $case->service_description ?? $case->case_description ?? '',
            $case->level_of_care,
            number_format($case->price, 2),
            $case->group,
            $case->pa_required ? 'Yes' : 'No',
            $case->referable ? 'Yes' : 'No',
            $case->is_bundle ? 'Yes' : 'No',
            $case->is_bundle && $case->bundle_price ? number_format($case->bundle_price, 2) : '',
            $case->diagnosis_icd10 ?? '',
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
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center']
            ],
        ];
    }

    /**
     * Register events for the export
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                if ($this->isTemplate) {
                    $sheet = $event->sheet->getDelegate();

                    // Add instructions as comments/notes
                    $sheet->getComment('A1')->getText()->createTextRun(
                        "INSTRUCTIONS:\n" .
                        "* = Required field\n" .
                        "Level of Care: Primary, Secondary, or Tertiary\n" .
                        "PA Required: Yes or No\n" .
                        "Referable: Yes or No\n" .
                        "Is Bundle: Yes (for bundle services) or No (for FFS services)\n" .
                        "Bundle Price: Required if Is Bundle = Yes\n" .
                        "ICD-10 Code: Required if Is Bundle = Yes (e.g., O80 for Normal Delivery)"
                    );

                    // Set column widths for better readability
                    $sheet->getColumnDimension('A')->setWidth(30); // Case Name
                    $sheet->getColumnDimension('B')->setWidth(50); // Service Description
                    $sheet->getColumnDimension('C')->setWidth(15); // Level of Care
                    $sheet->getColumnDimension('D')->setWidth(15); // Price
                    $sheet->getColumnDimension('E')->setWidth(30); // Group
                    $sheet->getColumnDimension('F')->setWidth(15); // PA Required
                    $sheet->getColumnDimension('G')->setWidth(15); // Referable
                    $sheet->getColumnDimension('H')->setWidth(15); // Is Bundle
                    $sheet->getColumnDimension('I')->setWidth(15); // Bundle Price
                    $sheet->getColumnDimension('J')->setWidth(15); // ICD-10 Code

                    // Add data validation for specific columns
                    // Apply validation row by row to avoid worksheet binding issues

                    // Level of Care dropdown (C2:C100)
                    for ($row = 2; $row <= 100; $row++) {
                        $validation = $sheet->getCell('C' . $row)->getDataValidation();
                        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
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

                    // Yes/No dropdowns for PA Required (F), Referable (G), Is Bundle (H)
                    foreach (['F', 'G', 'H'] as $column) {
                        for ($row = 2; $row <= 100; $row++) {
                            $validation = $sheet->getCell($column . $row)->getDataValidation();
                            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                            $validation->setAllowBlank(true);
                            $validation->setShowInputMessage(true);
                            $validation->setShowErrorMessage(true);
                            $validation->setShowDropDown(true);
                            $validation->setErrorTitle('Invalid Value');
                            $validation->setError('Please select Yes or No.');
                            $validation->setPromptTitle('Select Value');
                            $validation->setPrompt('Select: Yes or No');
                            $validation->setFormula1('"Yes,No"');
                        }
                    }
                }
            },
        ];
    }
}

