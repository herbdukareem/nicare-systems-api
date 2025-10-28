<?php

namespace App\Exports;

use App\Models\TariffItem;
use App\Models\CaseCategory;
use App\Models\ServiceType;
use App\Models\CaseType;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TariffItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected array $filters;
    protected bool $isTemplate;

    public function __construct(array $filters = [], bool $isTemplate = false)
    {
        $this->filters = $filters;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Get collection of tariff items or template data
     */
    public function collection()
    {
        if ($this->isTemplate) {
            return $this->getTemplateData();
        }

        $query = TariffItem::with(['caseCategory', 'serviceType', 'caseType']);

        // Apply filters
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('tariff_item', 'like', "%{$search}%")
                  ->orWhereHas('caseCategory', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (isset($this->filters['status']) && $this->filters['status'] !== '') {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['case_category_id'])) {
            $query->where('case_id', $this->filters['case_category_id']);
        }

        if (!empty($this->filters['service_type_id'])) {
            $query->where('service_type_id', $this->filters['service_type_id']);
        }

        if (!empty($this->filters['case_type_id'])) {
            $query->where('case_type_id', $this->filters['case_type_id']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get template data with sample rows
     */
    protected function getTemplateData()
    {
        $sampleData = collect();

        // Get first case category, service type, and case type for samples
        $caseCategory = CaseCategory::first();
        $serviceType = ServiceType::where('name', 'Professional Fees')->first();
        $caseType = CaseType::where('name', 'Surgical')->first();

        if ($caseCategory && $serviceType && $caseType) {
            // Sample 1: Surgical Professional Fee
            $sample1 = new TariffItem([
                'case_id' => $caseCategory->id,
                'service_type_id' => $serviceType->id,
                'tariff_item' => 'Surgeon fee (Pre-Op consultations, intra-Op, Post-Op)',
                'price' => 0.00,
                'case_type_id' => $caseType->id,
                'status' => true
            ]);
            $sample1->setRelation('caseCategory', $caseCategory);
            $sample1->setRelation('serviceType', $serviceType);
            $sample1->setRelation('caseType', $caseType);
            $sampleData->push($sample1);

            // Sample 2: Anesthesiologist fee
            $sample2 = new TariffItem([
                'case_id' => $caseCategory->id,
                'service_type_id' => $serviceType->id,
                'tariff_item' => 'Anesthesiologist fee (Pre-Op consultations, intra-Op)',
                'price' => 0.00,
                'case_type_id' => $caseType->id,
                'status' => true
            ]);
            $sample2->setRelation('caseCategory', $caseCategory);
            $sample2->setRelation('serviceType', $serviceType);
            $sample2->setRelation('caseType', $caseType);
            $sampleData->push($sample2);

            // Sample 3: Hospital Stay
            $hospitalStay = ServiceType::where('name', 'Hospital Stay')->first();
            if ($hospitalStay) {
                $sample3 = new TariffItem([
                    'case_id' => $caseCategory->id,
                    'service_type_id' => $hospitalStay->id,
                    'tariff_item' => 'Ward fees',
                    'price' => 0.00,
                    'case_type_id' => $caseType->id,
                    'status' => true
                ]);
                $sample3->setRelation('caseCategory', $caseCategory);
                $sample3->setRelation('serviceType', $hospitalStay);
                $sample3->setRelation('caseType', $caseType);
                $sampleData->push($sample3);
            }
        }

        return $sampleData;
    }

    /**
     * Map tariff item to row
     */
    public function map($tariffItem): array
    {
        return [
            $tariffItem->caseCategory ? $tariffItem->caseCategory->code : '',
            $tariffItem->caseCategory ? $tariffItem->caseCategory->name : '',
            $tariffItem->serviceType ? $tariffItem->serviceType->name : '',
            $tariffItem->tariff_item,
            $tariffItem->price,
            $tariffItem->caseType ? $tariffItem->caseType->name : ''
        ];
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'CaseCode',
            'CaseName',
            'CaseCategory',
            'TarrifItem',
            'Price',
            'CaseType'
        ];
    }

    /**
     * Define column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 15,  // CaseCode
            'B' => 40,  // CaseName
            'C' => 30,  // CaseCategory
            'D' => 60,  // TarrifItem
            'E' => 15,  // Price
            'F' => 15   // CaseType
        ];
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Apply borders to all data cells
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 1) {
            $sheet->getStyle('A2:F' . $highestRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);

            // Format price column as currency
            $sheet->getStyle('E2:E' . $highestRow)->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        // Freeze header row
        $sheet->freezePane('A2');

        return [];
    }
}

