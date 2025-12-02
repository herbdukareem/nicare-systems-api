<?php

namespace Database\Seeders;

use App\Models\DocumentRequirement;
use Illuminate\Database\Seeder;

class DocumentRequirementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requirements = [
            // ==================== Referral Documents ====================
            [
                'request_type' => 'referral',
                'document_type' => 'referral_letter',
                'name' => 'Referral Letter',
                'description' => 'Official referral letter from the referring facility signed by the attending physician.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 5,
                'display_order' => 1,
            ],
            [
                'request_type' => 'referral',
                'document_type' => 'medical_report',
                'name' => 'Medical Report',
                'description' => 'Current medical report detailing the patient\'s condition and reason for referral.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 10,
                'display_order' => 2,
            ],
            [
                'request_type' => 'referral',
                'document_type' => 'lab_results',
                'name' => 'Laboratory Results',
                'description' => 'Recent laboratory test results relevant to the referral condition.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 10,
                'display_order' => 3,
            ],
            [
                'request_type' => 'referral',
                'document_type' => 'radiology_results',
                'name' => 'Radiology/Imaging Results',
                'description' => 'X-ray, ultrasound, CT scan, or MRI results if applicable.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png,dcm',
                'max_file_size_mb' => 20,
                'display_order' => 4,
            ],
            [
                'request_type' => 'referral',
                'document_type' => 'enrollee_id',
                'name' => 'Enrollee ID Card',
                'description' => 'Copy of the patient\'s NiCare ID card.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 2,
                'display_order' => 5,
            ],
            [
                'request_type' => 'referral',
                'document_type' => 'consent_form',
                'name' => 'Patient Consent Form',
                'description' => 'Signed consent form for referral and treatment.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 2,
                'display_order' => 6,
            ],

            // ==================== PA Code Documents ====================
            [
                'request_type' => 'pa_code',
                'document_type' => 'pa_request_form',
                'name' => 'PA Request Form',
                'description' => 'Completed Prior Authorization request form.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 5,
                'display_order' => 1,
            ],
            [
                'request_type' => 'pa_code',
                'document_type' => 'clinical_justification',
                'name' => 'Clinical Justification',
                'description' => 'Detailed clinical justification for the requested service or procedure.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 5,
                'display_order' => 2,
            ],
            [
                'request_type' => 'pa_code',
                'document_type' => 'treatment_plan',
                'name' => 'Treatment Plan',
                'description' => 'Proposed treatment plan for the patient.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 5,
                'display_order' => 3,
            ],
            [
                'request_type' => 'pa_code',
                'document_type' => 'cost_estimate',
                'name' => 'Cost Estimate',
                'description' => 'Itemized cost estimate for the requested services.',
                'is_required' => true,
                'allowed_file_types' => 'pdf,xlsx,xls',
                'max_file_size_mb' => 5,
                'display_order' => 4,
            ],
            [
                'request_type' => 'pa_code',
                'document_type' => 'supporting_lab_results',
                'name' => 'Supporting Lab Results',
                'description' => 'Laboratory results supporting the need for the requested service.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 10,
                'display_order' => 5,
            ],
            [
                'request_type' => 'pa_code',
                'document_type' => 'specialist_recommendation',
                'name' => 'Specialist Recommendation',
                'description' => 'Recommendation letter from a specialist if applicable.',
                'is_required' => false,
                'allowed_file_types' => 'pdf,jpg,jpeg,png',
                'max_file_size_mb' => 5,
                'display_order' => 6,
            ],
        ];

        foreach ($requirements as $requirement) {
            DocumentRequirement::updateOrCreate(
                [
                    'request_type' => $requirement['request_type'],
                    'document_type' => $requirement['document_type'],
                ],
                $requirement
            );
        }

        $this->command->info('Document requirements seeded successfully!');
    }
}

