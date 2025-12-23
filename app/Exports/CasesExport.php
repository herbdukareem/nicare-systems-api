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
            // Return sample data for template with all detail types
            return collect([
                $this->getDrugSample(),
                $this->getLabSample(),
                $this->getRadiologySample(),
                $this->getProfessionalServiceSample(),
                $this->getConsultationSample(),
                $this->getConsumableSample(),
                $this->getBundleSample(),
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
                // Basic Case Information
                'Case Name *',
                'Service Description *',
                'Level of Care *',
                'Price (₦)',
                'Group *',
                'PA Required',
                'Referable',
                'Is Bundle',
                'Bundle Price (₦)',
                'ICD-10 Code',

                // Detail Type Selection
                'Detail Type',

                // Drug Detail Fields
                'Drug: Generic Name',
                'Drug: Brand Name',
                'Drug: Dosage Form',
                'Drug: Strength',
                'Drug: Pack Description',
                'Drug: Route',
                'Drug: Manufacturer',
                'Drug: Drug Class',
                'Drug: NAFDAC Number',

                // Laboratory Detail Fields
                'Lab: Test Name',
                'Lab: Test Code',
                'Lab: Specimen Type',
                'Lab: Test Category',
                'Lab: Turnaround Time (hrs)',
                'Lab: Fasting Required',

                // Radiology Detail Fields
                'Radiology: Exam Name',
                'Radiology: Exam Code',
                'Radiology: Modality',
                'Radiology: Body Part',
                'Radiology: Contrast Required',
                'Radiology: Pregnancy Safe',

                // Professional Service Detail Fields
                'ProfService: Service Name',
                'ProfService: Service Code',
                'ProfService: Specialty',
                'ProfService: Duration (min)',
                'ProfService: Provider Type',
                'ProfService: Anesthesia Required',

                // Consultation Detail Fields
                'Consultation: Type',
                'Consultation: Specialty',
                'Consultation: Provider Level',
                'Consultation: Duration (min)',
                'Consultation: Mode',

                // Consumable Detail Fields
                'Consumable: Item Name',
                'Consumable: Item Code',
                'Consumable: Category',
                'Consumable: Unit of Measure',
                'Consumable: Units Per Pack',
                'Consumable: Sterile',
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
            'Detail Type',
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
            // For template, return all fields including detail type fields
            return [
                // Basic fields
                $case->case_name,
                $case->service_description,
                $case->level_of_care,
                $case->price,
                $case->group,
                $case->pa_required,
                $case->referable,
                $case->is_bundle,
                $case->bundle_price,
                $case->diagnosis_icd10,
                $case->detail_type ?? '',

                // Drug fields
                $case->drug_generic_name ?? '',
                $case->drug_brand_name ?? '',
                $case->drug_dosage_form ?? '',
                $case->drug_strength ?? '',
                $case->drug_pack_description ?? '',
                $case->drug_route ?? '',
                $case->drug_manufacturer ?? '',
                $case->drug_drug_class ?? '',
                $case->drug_nafdac_number ?? '',

                // Lab fields
                $case->lab_test_name ?? '',
                $case->lab_test_code ?? '',
                $case->lab_specimen_type ?? '',
                $case->lab_test_category ?? '',
                $case->lab_turnaround_time ?? '',
                $case->lab_fasting_required ?? '',

                // Radiology fields
                $case->radiology_exam_name ?? '',
                $case->radiology_exam_code ?? '',
                $case->radiology_modality ?? '',
                $case->radiology_body_part ?? '',
                $case->radiology_contrast_required ?? '',
                $case->radiology_pregnancy_safe ?? '',

                // Professional Service fields
                $case->profservice_service_name ?? '',
                $case->profservice_service_code ?? '',
                $case->profservice_specialty ?? '',
                $case->profservice_duration ?? '',
                $case->profservice_provider_type ?? '',
                $case->profservice_anesthesia_required ?? '',

                // Consultation fields
                $case->consultation_type ?? '',
                $case->consultation_specialty ?? '',
                $case->consultation_provider_level ?? '',
                $case->consultation_duration ?? '',
                $case->consultation_mode ?? '',

                // Consumable fields
                $case->consumable_item_name ?? '',
                $case->consumable_item_code ?? '',
                $case->consumable_category ?? '',
                $case->consumable_unit_of_measure ?? '',
                $case->consumable_units_per_pack ?? '',
                $case->consumable_sterile ?? '',
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

    /**
     * Get sample data for Drug detail type
     */
    private function getDrugSample(): object
    {
        return (object) [
            'case_name' => 'Paracetamol 500mg Tablets',
            'service_description' => 'Paracetamol 500mg Tablets - Pack of 20',
            'level_of_care' => 'Primary',
            'price' => 500.00,
            'group' => 'DRUGS',
            'pa_required' => 'No',
            'referable' => 'No',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'Drug',
            'drug_generic_name' => 'Paracetamol',
            'drug_brand_name' => 'Panadol',
            'drug_dosage_form' => 'Tablet',
            'drug_strength' => '500mg',
            'drug_pack_description' => 'Pack of 20 tablets',
            'drug_route' => 'Oral',
            'drug_manufacturer' => 'GSK',
            'drug_drug_class' => 'Analgesic',
            'drug_nafdac_number' => 'A4-1234',
            // Empty other detail fields
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }

    /**
     * Get sample data for Laboratory detail type
     */
    private function getLabSample(): object
    {
        return (object) [
            'case_name' => 'Full Blood Count (FBC)',
            'service_description' => 'Complete blood count with differential',
            'level_of_care' => 'Primary',
            'price' => 2500.00,
            'group' => 'LABORATORY',
            'pa_required' => 'No',
            'referable' => 'No',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'Laboratory',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => 'Full Blood Count',
            'lab_test_code' => 'FBC001',
            'lab_specimen_type' => 'Whole Blood',
            'lab_test_category' => 'Hematology',
            'lab_turnaround_time' => '2',
            'lab_fasting_required' => 'No',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }

    /**
     * Get sample data for Radiology detail type
     */
    private function getRadiologySample(): object
    {
        return (object) [
            'case_name' => 'Chest X-Ray',
            'service_description' => 'Chest X-Ray - PA View',
            'level_of_care' => 'Secondary',
            'price' => 5000.00,
            'group' => 'RADIOLOGY',
            'pa_required' => 'Yes',
            'referable' => 'Yes',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'Radiology',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => 'Chest X-Ray',
            'radiology_exam_code' => 'XR001',
            'radiology_modality' => 'X-Ray',
            'radiology_body_part' => 'Chest',
            'radiology_contrast_required' => 'No',
            'radiology_pregnancy_safe' => 'No',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }

    /**
     * Get sample data for Professional Service detail type
     */
    private function getProfessionalServiceSample(): object
    {
        return (object) [
            'case_name' => 'Minor Surgery - Wound Suturing',
            'service_description' => 'Minor surgical procedure for wound closure',
            'level_of_care' => 'Secondary',
            'price' => 15000.00,
            'group' => 'PROFESSIONAL SERVICES',
            'pa_required' => 'Yes',
            'referable' => 'Yes',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'ProfessionalService',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => 'Wound Suturing',
            'profservice_service_code' => 'PS001',
            'profservice_specialty' => 'General Surgery',
            'profservice_duration' => '30',
            'profservice_provider_type' => 'Specialist',
            'profservice_anesthesia_required' => 'Yes',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }

    /**
     * Get sample data for Consultation detail type
     */
    private function getConsultationSample(): object
    {
        return (object) [
            'case_name' => 'Specialist Consultation - Cardiology',
            'service_description' => 'Cardiology specialist consultation',
            'level_of_care' => 'Tertiary',
            'price' => 10000.00,
            'group' => 'CONSULTATIONS',
            'pa_required' => 'Yes',
            'referable' => 'Yes',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'Consultation',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => 'Initial',
            'consultation_specialty' => 'Cardiology',
            'consultation_provider_level' => 'Consultant',
            'consultation_duration' => '45',
            'consultation_mode' => 'In-person',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }

    /**
     * Get sample data for Consumable detail type
     */
    private function getConsumableSample(): object
    {
        return (object) [
            'case_name' => 'Surgical Gloves - Sterile',
            'service_description' => 'Sterile surgical gloves - Size 7.5',
            'level_of_care' => 'Primary',
            'price' => 1500.00,
            'group' => 'CONSUMABLES',
            'pa_required' => 'No',
            'referable' => 'No',
            'is_bundle' => 'No',
            'bundle_price' => '',
            'diagnosis_icd10' => '',
            'detail_type' => 'Consumable',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => 'Surgical Gloves',
            'consumable_item_code' => 'CONS001',
            'consumable_category' => 'Gloves',
            'consumable_unit_of_measure' => 'Pair',
            'consumable_units_per_pack' => '50',
            'consumable_sterile' => 'Yes',
        ];
    }

    /**
     * Get sample data for Bundle
     */
    private function getBundleSample(): object
    {
        return (object) [
            'case_name' => 'Normal Delivery Bundle',
            'service_description' => 'Normal Delivery - Complete Package',
            'level_of_care' => 'Secondary',
            'price' => 0.00,
            'group' => 'OBSTETRICS & GYNAECOLOGY',
            'pa_required' => 'Yes',
            'referable' => 'Yes',
            'is_bundle' => 'Yes',
            'bundle_price' => 50000.00,
            'diagnosis_icd10' => 'O80',
            'detail_type' => '',
            'drug_generic_name' => '',
            'drug_brand_name' => '',
            'drug_dosage_form' => '',
            'drug_strength' => '',
            'drug_pack_description' => '',
            'drug_route' => '',
            'drug_manufacturer' => '',
            'drug_drug_class' => '',
            'drug_nafdac_number' => '',
            'lab_test_name' => '',
            'lab_test_code' => '',
            'lab_specimen_type' => '',
            'lab_test_category' => '',
            'lab_turnaround_time' => '',
            'lab_fasting_required' => '',
            'radiology_exam_name' => '',
            'radiology_exam_code' => '',
            'radiology_modality' => '',
            'radiology_body_part' => '',
            'radiology_contrast_required' => '',
            'radiology_pregnancy_safe' => '',
            'profservice_service_name' => '',
            'profservice_service_code' => '',
            'profservice_specialty' => '',
            'profservice_duration' => '',
            'profservice_provider_type' => '',
            'profservice_anesthesia_required' => '',
            'consultation_type' => '',
            'consultation_specialty' => '',
            'consultation_provider_level' => '',
            'consultation_duration' => '',
            'consultation_mode' => '',
            'consumable_item_name' => '',
            'consumable_item_code' => '',
            'consumable_category' => '',
            'consumable_unit_of_measure' => '',
            'consumable_units_per_pack' => '',
            'consumable_sterile' => '',
        ];
    }
}

