<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CapitationHistoricalPaymentReportExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $summary,
    ) {
    }

    public function array(): array
    {
        $title = sprintf(
            'CAPITATION %s REPORT - %s (%s)',
            strtoupper((string) ($this->summary['scope_label'] ?? 'History')),
            strtoupper((string) ($this->summary['range_label'] ?? 'All Time')),
            (string) ($this->summary['status_label'] ?? 'All statuses'),
        );

        $headings = $this->headings();
        $data = $this->rows->values()->map(function (array $row, int $index): array {
            return $this->mapRow($row, $index + 1);
        })->all();

        return array_merge([[$title], $headings], $data);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $headings = $this->headings();
                $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
                $lastRow = max(2, $this->rows->count() + 2);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '0F766E']],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                foreach ($this->numericColumnIndexes() as $columnIndex) {
                    $column = Coordinate::stringFromColumnIndex($columnIndex);
                    $sheet->getStyle("{$column}3:{$column}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("{$column}3:{$column}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                }

                $sheet->freezePane('A3');
            },
        ];
    }

    private function headings(): array
    {
        if (($this->summary['scope'] ?? null) === 'facility_history') {
            return [
                'S/N',
                'Facility',
                'Facility Code',
                'LGA',
                'Ward',
                'Capitation Period',
                'Cutoff Date',
                'Processing Status',
                'Funding Types',
                'Total Enrollees',
                'BHCPF',
                'NiCare',
                'BHCPF-CF',
                'GAC',
                'NiCare-Formal',
                'Unicef',
                'Total Amount',
            ];
        }

        return [
            'S/N',
            'Facility',
            'Facility Code',
            'LGA',
            'Ward',
            'First Capitation Period',
            'Last Capitation Period',
            'Period Count',
            'Processing Status',
            'Funding Types',
            'Total Enrollees',
            'BHCPF',
            'NiCare',
            'BHCPF-CF',
            'GAC',
            'NiCare-Formal',
            'Unicef',
            'Total Amount',
        ];
    }

    private function mapRow(array $row, int $serialNumber): array
    {
        if (($this->summary['scope'] ?? null) === 'facility_history') {
            return [
                $serialNumber,
                $row['provider_name'] ?? 'N/A',
                $row['facility_code'] ?? '',
                $row['lga'] ?? '',
                $row['ward'] ?? '',
                $row['capitation_period'] ?? 'N/A',
                $row['cutoff_date'] ?? '',
                $row['processing_status'] ?? 'Unknown',
                $row['funding_type_summary'] ?? 'N/A',
                (int) ($row['total_enrollees'] ?? 0),
                (float) ($row['bhcpf'] ?? 0),
                (float) ($row['nicare'] ?? 0),
                (float) ($row['bhcpf_cf'] ?? 0),
                (float) ($row['gac'] ?? 0),
                (float) ($row['nicare_formal'] ?? 0),
                (float) ($row['unicef'] ?? 0),
                (float) ($row['total_amount'] ?? 0),
            ];
        }

        return [
            $serialNumber,
            $row['provider_name'] ?? 'N/A',
            $row['facility_code'] ?? '',
            $row['lga'] ?? '',
            $row['ward'] ?? '',
            $row['first_capitation_period'] ?? '',
            $row['last_capitation_period'] ?? '',
            (int) ($row['period_count'] ?? 0),
            $row['processing_status'] ?? 'Unknown',
            $row['funding_type_summary'] ?? 'N/A',
            (int) ($row['total_enrollees'] ?? 0),
            (float) ($row['bhcpf'] ?? 0),
            (float) ($row['nicare'] ?? 0),
            (float) ($row['bhcpf_cf'] ?? 0),
            (float) ($row['gac'] ?? 0),
            (float) ($row['nicare_formal'] ?? 0),
            (float) ($row['unicef'] ?? 0),
            (float) ($row['total_amount'] ?? 0),
        ];
    }

    private function numericColumnIndexes(): array
    {
        if (($this->summary['scope'] ?? null) === 'facility_history') {
            return [10, 11, 12, 13, 14, 15, 16, 17];
        }

        return [8, 11, 12, 13, 14, 15, 16, 17, 18];
    }
}
