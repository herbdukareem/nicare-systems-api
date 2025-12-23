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

class ConsultationCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'Specialist Consultation - Cardiology',
                    'nicare_code' => 'NGSCHA/CONS/S/0001',
                    'service_description' => 'Specialist Consultation - Cardiology',
                    'level_of_care' => 'Tertiary',
                    'price' => 10000.00,
                    'group' => 'CONSULTATION',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'consultation_type' => 'Initial',
                    'specialty' => 'Cardiology',
                    'provider_level' => 'Consultant',
                    'duration_minutes' => 30,
                    'consultation_mode' => 'In-person',
                ],
                (object) [
                    'case_name' => 'General Consultation',
                    'nicare_code' => 'NGSCHA/CONS/P/0001',
                    'service_description' => 'General Medical Consultation',
                    'level_of_care' => 'Primary',
                    'price' => 3000.00,
                    'group' => 'CONSULTATION',
                    'pa_required' => 'No',
                    'referable' => 'Yes',
                    'consultation_type' => 'Initial',
                    'specialty' => 'General Practice',
                    'provider_level' => 'Medical Officer',
                    'duration_minutes' => 20,
                    'consultation_mode' => 'In-person',
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
            'Consultation Type *',
            'Specialty',
            'Provider Level',
            'Duration (minutes)',
            'Consultation Mode',
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
            $case->group ?? 'CONSULTATION',
            $case->pa_required ?? 'No',
            $case->referable ?? 'Yes',
            $case->consultation_type ?? '',
            $case->specialty ?? '',
            $case->provider_level ?? '',
            $case->duration_minutes ?? '',
            $case->consultation_mode ?? '',
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

