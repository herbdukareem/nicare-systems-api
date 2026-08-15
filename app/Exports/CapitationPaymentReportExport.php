<?php

namespace App\Exports;

use App\Models\Capitation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CapitationPaymentReportExport implements FromArray, ShouldAutoSize, WithEvents
{
    public function __construct(
        private readonly Capitation $capitation,
        private readonly Collection $rows,
        private readonly string $statusLabel,
    ) {
    }

    public function array(): array
    {
        $title = sprintf(
            'CAPITATION PAYMENT DETAILS - %s (%s)',
            strtoupper((string) $this->capitation->name),
            $this->statusLabel,
        );

        $headings = [
            'S/N', 'Provider Name', 'Facility Code', 'LGA', 'Ward', 'Total Enrollees',
            'BHCPF', 'NiCare', 'BHCPF-CF', 'GAC', 'NiCare-Formal', 'Unicef', 'Total Amount',
        ];

        $data = $this->rows->values()->map(function (array $row, int $index): array {
            return [
                $index + 1,
                $row['provider_name'],
                $row['facility_code'],
                $row['lga'],
                $row['ward'],
                $row['total_enrollees'],
                $row['bhcpf'],
                $row['nicare'],
                $row['bhcpf_cf'],
                $row['gac'],
                $row['nicare_formal'],
                $row['unicef'],
                $row['total_amount'],
            ];
        })->all();

        return array_merge([[$title], $headings], $data);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(2, $this->rows->count() + 2);

                $sheet->mergeCells('A1:M1');
                $sheet->getStyle('A1:M1')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '43A047']],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A2:M2')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("A2:M{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("F3:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("G3:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->freezePane('A3');
            },
        ];
    }
}
