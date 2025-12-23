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

class ProfessionalServiceCasesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
                    'case_name' => 'Minor Surgery - Wound Suturing',
                    'nicare_code' => 'NGSCHA/PROF/S/0001',
                    'service_description' => 'Minor Surgical Procedure - Wound Suturing',
                    'level_of_care' => 'Secondary',
                    'price' => 15000.00,
                    'group' => 'PROFESSIONAL SERVICES',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'service_name' => 'Wound Suturing',
                    'service_code' => 'PS001',
                    'specialty' => 'General Surgery',
                    'duration_minutes' => 30,
                    'provider_type' => 'Specialist',
                    'anesthesia_required' => 'Yes',
                ],
                (object) [
                    'case_name' => 'Incision and Drainage',
                    'nicare_code' => 'NGSCHA/PROF/S/0002',
                    'service_description' => 'Incision and Drainage of Abscess',
                    'level_of_care' => 'Secondary',
                    'price' => 12000.00,
                    'group' => 'PROFESSIONAL SERVICES',
                    'pa_required' => 'Yes',
                    'referable' => 'Yes',
                    'service_name' => 'Incision and Drainage',
                    'service_code' => 'PS002',
                    'specialty' => 'General Surgery',
                    'duration_minutes' => 20,
                    'provider_type' => 'Specialist',
                    'anesthesia_required' => 'Yes',
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
            'Service Name *',
            'Service Code',
            'Specialty',
            'Duration (minutes)',
            'Provider Type',
            'Anesthesia Required',
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
            $case->group ?? 'PROFESSIONAL SERVICES',
            $case->pa_required ?? 'Yes',
            $case->referable ?? 'Yes',
            $case->service_name ?? '',
            $case->service_code ?? '',
            $case->specialty ?? '',
            $case->duration_minutes ?? '',
            $case->provider_type ?? '',
            $case->anesthesia_required ?? 'No',
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

