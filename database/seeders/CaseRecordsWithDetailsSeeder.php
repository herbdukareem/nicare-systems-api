<?php

namespace Database\Seeders;

use App\Models\CaseRecord;
use App\Models\DrugDetail;
use App\Models\LaboratoryDetail;
use App\Models\ProfessionalServiceDetail;
use App\Models\RadiologyDetail;
use App\Models\ConsultationDetail;
use App\Models\ConsumableDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CaseRecordsWithDetailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates case records with all 6 polymorphic detail types:
     * - Drug
     * - Laboratory
     * - Professional Service
     * - Radiology
     * - Consultation
     * - Consumable
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('Creating case records with polymorphic details...');

            // 1. Drug Cases
            $this->createDrugCases();

            // 2. Laboratory Cases
            $this->createLaboratoryCases();

            // 3. Professional Service Cases
            $this->createProfessionalServiceCases();

            // 4. Radiology Cases
            $this->createRadiologyCases();

            // 5. Consultation Cases
            $this->createConsultationCases();

            // 6. Consumable Cases
            $this->createConsumableCases();

            DB::commit();

            $this->command->info('✅ Successfully created case records with all detail types!');
            $this->command->info('Total cases created: ' . CaseRecord::count());

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error creating case records: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create drug cases with DrugDetail
     */
    private function createDrugCases(): void
    {
        $drugs = [
            [
                'case_name' => 'Paracetamol 500mg Tablet',
                'service_description' => 'Paracetamol 500mg oral tablet for pain and fever relief',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 50.00,
                'pa_required' => false,
                'detail' => [
                    'generic_name' => 'Paracetamol',
                    'brand_name' => 'Panadol',
                    'dosage_form' => 'Tablet',
                    'strength' => '500mg',
                    'route_of_administration' => 'Oral',
                    'manufacturer' => 'GSK Nigeria',
                    'drug_class' => 'Analgesic/Antipyretic',
                    'indications' => 'Pain relief, fever reduction',
                    'contraindications' => 'Severe liver disease, hypersensitivity',
                    'side_effects' => 'Rare: skin rash, liver damage with overdose',
                    'storage_conditions' => 'Store below 25°C in dry place',
                    'prescription_required' => false,
                    'controlled_substance' => false,
                    'nafdac_number' => 'A4-1234',
                    'expiry_date' => now()->addYears(2)->format('Y-m-d'),
                ],
            ],
            [
                'case_name' => 'Amoxicillin 500mg Capsule',
                'service_description' => 'Amoxicillin 500mg capsule - broad spectrum antibiotic',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 150.00,
                'pa_required' => false,
                'detail' => [
                    'generic_name' => 'Amoxicillin',
                    'brand_name' => 'Amoxil',
                    'dosage_form' => 'Capsule',
                    'strength' => '500mg',
                    'route_of_administration' => 'Oral',
                    'manufacturer' => 'Pfizer Nigeria',
                    'drug_class' => 'Penicillin Antibiotic',
                    'indications' => 'Bacterial infections - respiratory, urinary, skin',
                    'contraindications' => 'Penicillin allergy, infectious mononucleosis',
                    'side_effects' => 'Diarrhea, nausea, skin rash, allergic reactions',
                    'storage_conditions' => 'Store at room temperature, protect from moisture',
                    'prescription_required' => true,
                    'controlled_substance' => false,
                    'nafdac_number' => 'A4-5678',
                    'expiry_date' => now()->addYears(3)->format('Y-m-d'),
                ],
            ],
        ];

        foreach ($drugs as $drugData) {
            $detail = $drugData['detail'];
            unset($drugData['detail']);

            // Create drug detail
            $drugDetail = DrugDetail::create($detail);

            // Create case record with polymorphic relationship
            $drugData['detail_id'] = $drugDetail->id;
            $drugData['detail_type'] = DrugDetail::class;
            $drugData['nicare_code'] = CaseRecord::generateNiCareCode($drugData['case_name'], $drugData['level_of_care']);
            $drugData['status'] = true;

            CaseRecord::create($drugData);
        }

        $this->command->info('✓ Created ' . count($drugs) . ' drug cases');
    }

    /**
     * Create laboratory cases with LaboratoryDetail
     */
    private function createLaboratoryCases(): void
    {
        $labs = [
            [
                'case_name' => 'Full Blood Count (FBC)',
                'service_description' => 'Complete blood count with differential',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 2500.00,
                'pa_required' => false,
                'detail' => [
                    'test_name' => 'Full Blood Count',
                    'test_code' => 'FBC001',
                    'specimen_type' => 'Whole Blood',
                    'specimen_volume' => '3-5ml EDTA blood',
                    'collection_method' => 'Venipuncture',
                    'test_method' => 'Automated Hematology Analyzer',
                    'test_category' => 'Hematology',
                    'turnaround_time' => 120,
                    'preparation_instructions' => 'No special preparation required',
                    'reference_range' => 'WBC: 4-11 x10^9/L, RBC: 4.5-5.5 x10^12/L, Hb: 12-16 g/dL',
                    'reporting_unit' => 'x10^9/L, g/dL, %',
                    'fasting_required' => false,
                    'urgent_available' => true,
                    'urgent_surcharge' => 1000.00,
                ],
            ],
            [
                'case_name' => 'Malaria Parasite Test',
                'service_description' => 'Microscopic examination for malaria parasites',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 1000.00,
                'pa_required' => false,
                'detail' => [
                    'test_name' => 'Malaria Parasite (MP)',
                    'test_code' => 'MP001',
                    'specimen_type' => 'Whole Blood',
                    'specimen_volume' => '2ml EDTA blood',
                    'collection_method' => 'Finger prick or venipuncture',
                    'test_method' => 'Microscopy - Thick and Thin Film',
                    'test_category' => 'Microbiology',
                    'turnaround_time' => 60,
                    'preparation_instructions' => 'No special preparation',
                    'reference_range' => 'Negative',
                    'reporting_unit' => 'Positive/Negative, Parasites/µL',
                    'fasting_required' => false,
                    'urgent_available' => true,
                    'urgent_surcharge' => 500.00,
                ],
            ],
            [
                'case_name' => 'Lipid Profile',
                'service_description' => 'Comprehensive cholesterol and lipid panel',
                'level_of_care' => CaseRecord::LEVEL_SECONDARY,
                'price' => 4500.00,
                'pa_required' => false,
                'detail' => [
                    'test_name' => 'Lipid Profile',
                    'test_code' => 'LIPID001',
                    'specimen_type' => 'Serum',
                    'specimen_volume' => '5ml clotted blood',
                    'collection_method' => 'Venipuncture',
                    'test_method' => 'Enzymatic Colorimetric',
                    'test_category' => 'Clinical Chemistry',
                    'turnaround_time' => 180,
                    'preparation_instructions' => '12-14 hours fasting required',
                    'reference_range' => 'Total Cholesterol: <200mg/dL, LDL: <100mg/dL, HDL: >40mg/dL, TG: <150mg/dL',
                    'reporting_unit' => 'mg/dL',
                    'fasting_required' => true,
                    'urgent_available' => false,
                    'urgent_surcharge' => null,
                ],
            ],
        ];

        foreach ($labs as $labData) {
            $detail = $labData['detail'];
            unset($labData['detail']);

            $labDetail = LaboratoryDetail::create($detail);

            $labData['detail_id'] = $labDetail->id;
            $labData['detail_type'] = LaboratoryDetail::class;
            $labData['nicare_code'] = CaseRecord::generateNiCareCode($labData['case_name'], $labData['level_of_care']);
            $labData['status'] = true;

            CaseRecord::create($labData);
        }

        $this->command->info('✓ Created ' . count($labs) . ' laboratory cases');
    }

    /**
     * Create professional service cases with ProfessionalServiceDetail
     */
    private function createProfessionalServiceCases(): void
    {
        $services = [
            [
                'case_name' => 'Minor Surgery - Abscess Drainage',
                'service_description' => 'Incision and drainage of superficial abscess',
                'level_of_care' => CaseRecord::LEVEL_SECONDARY,
                'price' => 15000.00,
                'pa_required' => true,
                'detail' => [
                    'service_name' => 'Abscess Incision and Drainage',
                    'service_code' => 'SURG001',
                    'specialty' => 'General Surgery',
                    'duration_minutes' => 30,
                    'provider_type' => 'Medical Officer',
                    'equipment_needed' => 'Sterile surgical kit, local anesthetic, dressing materials',
                    'procedure_description' => 'Local anesthesia, incision, drainage, packing, dressing',
                    'indications' => 'Superficial abscess, localized infection',
                    'contraindications' => 'Deep abscess requiring general anesthesia, bleeding disorders',
                    'complications' => 'Bleeding, infection, scarring, recurrence',
                    'pre_procedure_requirements' => 'Informed consent, vital signs check, allergy history',
                    'post_procedure_care' => 'Daily dressing change, antibiotics, pain relief, follow-up in 3 days',
                    'anesthesia_required' => true,
                    'anesthesia_type' => 'Local',
                    'admission_required' => false,
                    'recovery_time_hours' => 2,
                    'follow_up_required' => true,
                ],
            ],
        ];

        foreach ($services as $serviceData) {
            $detail = $serviceData['detail'];
            unset($serviceData['detail']);

            $serviceDetail = ProfessionalServiceDetail::create($detail);

            $serviceData['detail_id'] = $serviceDetail->id;
            $serviceData['detail_type'] = ProfessionalServiceDetail::class;
            $serviceData['nicare_code'] = CaseRecord::generateNiCareCode($serviceData['case_name'], $serviceData['level_of_care']);
            $serviceData['status'] = true;

            CaseRecord::create($serviceData);
        }

        $this->command->info('✓ Created ' . count($services) . ' professional service cases');
    }

    /**
     * Create radiology cases with RadiologyDetail
     */
    private function createRadiologyCases(): void
    {
        $radiologies = [
            [
                'case_name' => 'Chest X-Ray (PA View)',
                'service_description' => 'Chest radiograph - posteroanterior view',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 3500.00,
                'pa_required' => false,
                'detail' => [
                    'examination_name' => 'Chest X-Ray PA',
                    'examination_code' => 'XRAY001',
                    'modality' => 'X-Ray',
                    'body_part' => 'Chest',
                    'view_projection' => 'Posteroanterior (PA)',
                    'contrast_required' => false,
                    'contrast_type' => null,
                    'preparation_instructions' => 'Remove metallic objects, jewelry. No special preparation.',
                    'duration_minutes' => 10,
                    'indications' => 'Respiratory symptoms, cardiac assessment, pre-operative screening',
                    'contraindications' => 'Pregnancy (relative), inability to stand',
                    'pregnancy_safe' => false,
                    'radiation_dose' => 'Very Low',
                    'turnaround_time' => 120,
                    'urgent_available' => true,
                    'urgent_surcharge' => 1500.00,
                    'special_equipment' => 'Digital X-Ray machine',
                    'sedation_required' => false,
                ],
            ],
            [
                'case_name' => 'Abdominal Ultrasound',
                'service_description' => 'Ultrasound examination of abdomen and pelvis',
                'level_of_care' => CaseRecord::LEVEL_SECONDARY,
                'price' => 8000.00,
                'detail' => [
                    'examination_name' => 'Abdominal Ultrasound',
                    'examination_code' => 'USS001',
                    'modality' => 'Ultrasound',
                    'body_part' => 'Abdomen',
                    'view_projection' => 'Multiple views',
                    'contrast_required' => false,
                    'contrast_type' => null,
                    'preparation_instructions' => '6-8 hours fasting. Full bladder required.',
                    'duration_minutes' => 30,
                    'indications' => 'Abdominal pain, organ assessment, pregnancy monitoring',
                    'contraindications' => 'None significant',
                    'pregnancy_safe' => true,
                    'radiation_dose' => 'None',
                    'turnaround_time' => 180,
                    'urgent_available' => true,
                    'urgent_surcharge' => 3000.00,
                    'special_equipment' => 'Ultrasound machine with abdominal probe',
                    'sedation_required' => false,
                ],
            ],
            [
                'case_name' => 'CT Scan Brain (Non-Contrast)',
                'service_description' => 'Computed tomography of brain without contrast',
                'level_of_care' => CaseRecord::LEVEL_TERTIARY,
                'price' => 35000.00,
                'pa_required' => true,
                'detail' => [
                    'examination_name' => 'CT Brain Non-Contrast',
                    'examination_code' => 'CT001',
                    'modality' => 'CT Scan',
                    'body_part' => 'Brain',
                    'view_projection' => 'Axial slices',
                    'contrast_required' => false,
                    'contrast_type' => null,
                    'preparation_instructions' => 'No special preparation. Remove metallic objects.',
                    'duration_minutes' => 15,
                    'indications' => 'Head trauma, stroke, headache, neurological symptoms',
                    'contraindications' => 'Pregnancy (relative), patient unable to lie still',
                    'pregnancy_safe' => false,
                    'radiation_dose' => 'Medium',
                    'turnaround_time' => 240,
                    'urgent_available' => true,
                    'urgent_surcharge' => 15000.00,
                    'special_equipment' => 'CT Scanner',
                    'sedation_required' => false,
                ],
            ],
        ];

        foreach ($radiologies as $radiologyData) {
            $detail = $radiologyData['detail'];
            unset($radiologyData['detail']);

            $radiologyDetail = RadiologyDetail::create($detail);

            $radiologyData['detail_id'] = $radiologyDetail->id;
            $radiologyData['detail_type'] = RadiologyDetail::class;
            $radiologyData['nicare_code'] = CaseRecord::generateNiCareCode($radiologyData['case_name'], $radiologyData['level_of_care']);
            $radiologyData['status'] = true;

            CaseRecord::create($radiologyData);
        }

        $this->command->info('✓ Created ' . count($radiologies) . ' radiology cases');
    }

    /**
     * Create consultation cases with ConsultationDetail
     */
    private function createConsultationCases(): void
    {
        $consultations = [
            [
                'case_name' => 'General Practice Consultation',
                'service_description' => 'Initial consultation with general practitioner',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 2000.00,
                'pa_required' => false,
                'detail' => [
                    'consultation_type' => 'Initial Consultation',
                    'specialty' => 'General Practice',
                    'provider_level' => 'General Practitioner',
                    'duration_minutes' => 20,
                    'consultation_mode' => 'In-person',
                    'scope_of_service' => 'History taking, physical examination, diagnosis, treatment plan',
                    'diagnostic_tests_included' => false,
                    'included_services' => 'Vital signs check, basic physical examination',
                    'prescription_included' => true,
                    'medical_report_included' => false,
                    'referral_letter_included' => false,
                    'follow_up_required' => false,
                    'follow_up_interval_days' => null,
                    'emergency_available' => true,
                    'booking_requirements' => 'Walk-in or appointment',
                    'insurance_accepted' => true,
                ],
            ],
            [
                'case_name' => 'Cardiology Specialist Consultation',
                'service_description' => 'Specialist consultation with cardiologist',
                'level_of_care' => CaseRecord::LEVEL_TERTIARY,
                'price' => 15000.00,
                'pa_required' => true,
                'detail' => [
                    'consultation_type' => 'Initial Consultation',
                    'specialty' => 'Cardiology',
                    'provider_level' => 'Consultant',
                    'duration_minutes' => 45,
                    'consultation_mode' => 'In-person',
                    'scope_of_service' => 'Detailed cardiac history, cardiovascular examination, ECG interpretation, treatment plan',
                    'diagnostic_tests_included' => true,
                    'included_services' => 'ECG, vital signs, cardiovascular examination',
                    'prescription_included' => true,
                    'medical_report_included' => true,
                    'referral_letter_included' => false,
                    'follow_up_required' => true,
                    'follow_up_interval_days' => 30,
                    'emergency_available' => true,
                    'booking_requirements' => 'Referral letter required, advance booking',
                    'insurance_accepted' => true,
                ],
            ],
            [
                'case_name' => 'Pediatric Follow-up Consultation',
                'service_description' => 'Follow-up consultation for pediatric patients',
                'level_of_care' => CaseRecord::LEVEL_SECONDARY,
                'price' => 5000.00,
                'pa_required' => false,
                'detail' => [
                    'consultation_type' => 'Follow-up Consultation',
                    'specialty' => 'Pediatrics',
                    'provider_level' => 'Specialist',
                    'duration_minutes' => 30,
                    'consultation_mode' => 'In-person',
                    'scope_of_service' => 'Review of treatment progress, growth monitoring, adjustment of treatment plan',
                    'diagnostic_tests_included' => false,
                    'included_services' => 'Weight, height, vital signs, developmental assessment',
                    'prescription_included' => true,
                    'medical_report_included' => false,
                    'referral_letter_included' => false,
                    'follow_up_required' => true,
                    'follow_up_interval_days' => 14,
                    'emergency_available' => false,
                    'booking_requirements' => 'Previous consultation record required',
                    'insurance_accepted' => true,
                ],
            ],
        ];

        foreach ($consultations as $consultationData) {
            $detail = $consultationData['detail'];
            unset($consultationData['detail']);

            $consultationDetail = ConsultationDetail::create($detail);

            $consultationData['detail_id'] = $consultationDetail->id;
            $consultationData['detail_type'] = ConsultationDetail::class;
            $consultationData['nicare_code'] = CaseRecord::generateNiCareCode($consultationData['case_name'], $consultationData['level_of_care']);
            $consultationData['status'] = true;

            CaseRecord::create($consultationData);
        }

        $this->command->info('✓ Created ' . count($consultations) . ' consultation cases');
    }

    /**
     * Create consumable cases with ConsumableDetail
     */
    private function createConsumableCases(): void
    {
        $consumables = [
            [
                'case_name' => 'Surgical Gloves (Sterile)',
                'service_description' => 'Sterile surgical gloves - size 7.5',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 500.00,
                'pa_required' => false,
                'detail' => [
                    'item_name' => 'Surgical Gloves Sterile',
                    'item_code' => 'GLOVE001',
                    'category' => 'Gloves',
                    'subcategory' => 'Surgical Gloves',
                    'unit_of_measure' => 'Pair',
                    'units_per_pack' => 50,
                    'manufacturer' => 'Ansell Healthcare',
                    'material_composition' => 'Natural rubber latex',
                    'sterile' => true,
                    'sterilization_method' => 'Gamma Radiation',
                    'single_use' => true,
                    'latex_free' => false,
                    'specifications' => 'Size 7.5, powder-free, textured surface',
                    'usage_instructions' => 'Use aseptic technique when donning. Discard after single use.',
                    'storage_conditions' => 'Store in cool, dry place away from direct sunlight',
                    'expiry_date' => now()->addYears(5)->format('Y-m-d'),
                    'regulatory_approval' => 'NAFDAC',
                    'requires_cold_chain' => false,
                    'disposal_instructions' => 'Dispose in yellow biohazard bag',
                    'hazardous' => false,
                ],
            ],
            [
                'case_name' => 'IV Cannula 18G',
                'service_description' => 'Intravenous cannula 18 gauge with wings',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 300.00,
                'pa_required' => false,
                'detail' => [
                    'item_name' => 'IV Cannula 18G',
                    'item_code' => 'CANN001',
                    'category' => 'Catheters',
                    'subcategory' => 'IV Cannula',
                    'unit_of_measure' => 'Piece',
                    'units_per_pack' => 100,
                    'manufacturer' => 'BD Medical',
                    'material_composition' => 'Medical grade polyurethane',
                    'sterile' => true,
                    'sterilization_method' => 'Ethylene Oxide (ETO)',
                    'single_use' => true,
                    'latex_free' => true,
                    'specifications' => '18 gauge, 1.3mm x 45mm, color-coded green',
                    'usage_instructions' => 'Use aseptic technique. Insert at 15-30 degree angle. Secure with tape.',
                    'storage_conditions' => 'Store at room temperature in original packaging',
                    'expiry_date' => now()->addYears(3)->format('Y-m-d'),
                    'regulatory_approval' => 'FDA',
                    'requires_cold_chain' => false,
                    'disposal_instructions' => 'Dispose in sharps container',
                    'hazardous' => true,
                ],
            ],
            [
                'case_name' => 'Normal Saline 0.9% 1L',
                'service_description' => 'Sodium chloride 0.9% intravenous infusion 1 liter',
                'level_of_care' => CaseRecord::LEVEL_PRIMARY,
                'price' => 800.00,
                'pa_required' => false,
                'detail' => [
                    'item_name' => 'Normal Saline 0.9%',
                    'item_code' => 'FLUID001',
                    'category' => 'IV Fluids & Sets',
                    'subcategory' => 'Crystalloid Solutions',
                    'unit_of_measure' => 'Liter',
                    'units_per_pack' => 12,
                    'manufacturer' => 'Baxter Healthcare',
                    'material_composition' => 'Sodium Chloride 0.9% w/v in water for injection',
                    'sterile' => true,
                    'sterilization_method' => 'Autoclave',
                    'single_use' => true,
                    'latex_free' => true,
                    'specifications' => '1000ml flexible bag, non-PVC',
                    'usage_instructions' => 'Check for clarity and particulate matter. Use within 24 hours of opening.',
                    'storage_conditions' => 'Store at room temperature. Protect from freezing.',
                    'expiry_date' => now()->addYears(2)->format('Y-m-d'),
                    'regulatory_approval' => 'NAFDAC',
                    'requires_cold_chain' => false,
                    'disposal_instructions' => 'Empty bag can be disposed in general waste',
                    'hazardous' => false,
                ],
            ],
            [
                'case_name' => 'Suture Silk 2-0',
                'service_description' => 'Braided silk suture 2-0 with curved needle',
                'level_of_care' => CaseRecord::LEVEL_SECONDARY,
                'price' => 1200.00,
                'pa_required' => false,
                'detail' => [
                    'item_name' => 'Silk Suture 2-0',
                    'item_code' => 'SUT001',
                    'category' => 'Sutures',
                    'subcategory' => 'Non-absorbable Sutures',
                    'unit_of_measure' => 'Piece',
                    'units_per_pack' => 12,
                    'manufacturer' => 'Ethicon',
                    'material_composition' => 'Braided silk, coated',
                    'sterile' => true,
                    'sterilization_method' => 'Gamma Radiation',
                    'single_use' => true,
                    'latex_free' => true,
                    'specifications' => '2-0 gauge, 75cm length, 26mm 1/2 circle reverse cutting needle',
                    'usage_instructions' => 'Use aseptic technique. Remove sutures after 7-10 days.',
                    'storage_conditions' => 'Store in cool, dry place',
                    'expiry_date' => now()->addYears(5)->format('Y-m-d'),
                    'regulatory_approval' => 'CE Mark',
                    'requires_cold_chain' => false,
                    'disposal_instructions' => 'Dispose in sharps container',
                    'hazardous' => true,
                ],
            ],
        ];

        foreach ($consumables as $consumableData) {
            $detail = $consumableData['detail'];
            unset($consumableData['detail']);

            $consumableDetail = ConsumableDetail::create($detail);

            $consumableData['detail_id'] = $consumableDetail->id;
            $consumableData['detail_type'] = ConsumableDetail::class;
            $consumableData['nicare_code'] = CaseRecord::generateNiCareCode($consumableData['case_name'], $consumableData['level_of_care']);
            $consumableData['status'] = true;

            CaseRecord::create($consumableData);
        }

        $this->command->info('✓ Created ' . count($consumables) . ' consumable cases');
    }
}

