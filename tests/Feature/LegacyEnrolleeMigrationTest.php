<?php

namespace Tests\Feature;

use App\Models\Benefactor;
use App\Models\EnrolleeCategory;
use App\Models\Enrollee;
use App\Models\EnrollmentPhase;
use App\Models\Facility;
use App\Models\InsuranceProgramme;
use App\Models\LegacyMigrationLog;
use App\Models\Lga;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Models\Ward;
use App\Services\Legacy\LegacyEnrolleeMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LegacyEnrolleeMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\InsuranceProgrammeSeeder::class);
        $this->seedLegacyCategoryBuckets();
        User::factory()->create(['id' => 1]);
    }

    public function test_formal_enrollee_migration_creates_active_coverage(): void
    {
        $this->migrateLegacyFormal([
            'id' => 101,
            'enrolment_number' => 'FORM-001',
            'first_name' => 'Amina',
            'surname' => 'Bala',
            'funding' => 'payroll',
            'employer' => 'Niger State Civil Service',
            'basic_salary' => 100000,
        ]);

        $enrollee = Enrollee::where('enrollee_id', 'FORM-001')->firstOrFail();

        $this->assertTrue($enrollee->isActive());
        $this->assertNotNull($enrollee->coverage_start_date);
        $this->assertSame('Formal Sector Deduction', $enrollee->fundingType->name);
        $this->assertDatabaseHas('premium_purchases', [
            'payment_reference' => 'LEGACY-FORMAL-101-NO-PIN',
            'payment_status' => 'confirmed',
        ]);
    }

    public function test_bhcpf_enrollee_creates_vulnerable_coverage_with_bhcpf_funding(): void
    {
        $this->migrateLegacyInformal([
            'id' => 201,
            'enrolment_number' => 'BHC-001',
            'first_name' => 'Musa',
            'surname' => 'Ibrahim',
            'mode_of_enrolment' => 'BHCPF',
            'funding' => 'BHCPF',
            'BHCPF_number' => 'BHCPF-001',
        ]);

        $enrollee = Enrollee::where('enrollee_id', 'BHC-001')->firstOrFail();

        $this->assertTrue($enrollee->isActive());
        $this->assertSame('Basic Healthcare Provision Fund', $enrollee->fundingType->name);
        $this->assertSame('Vulnerable Groups', $enrollee->insuranceProgramme->name);
        $this->assertSame(0, PremiumPurchase::count());
    }

    public function test_pin_enrollee_creates_informal_coverage_and_purchase(): void
    {
        $this->migrateLegacyInformal([
            'id' => 301,
            'pin' => 'PIN12345',
            'enrolment_number' => 'PIN-001',
            'first_name' => 'Zainab',
            'surname' => 'Garba',
            'mode_of_enrolment' => 'Premium PIN',
            'funding' => 'premium',
        ]);

        $enrollee = Enrollee::where('enrollee_id', 'PIN-001')->firstOrFail();

        $this->assertTrue($enrollee->isActive());
        $this->assertSame('Premium', $enrollee->fundingType->name);
        $this->assertSame('Informal Sector', $enrollee->insuranceProgramme->name);
        $this->assertDatabaseHas('premium_purchases', [
            'payment_reference' => 'LEGACY-TBL_ENROLEE-301-PIN12345',
            'payment_status' => 'confirmed',
        ]);
    }

    public function test_legacy_tracking_maps_to_enrollment_phase(): void
    {
        $benefactor = Benefactor::firstOrCreate(
            ['name' => 'BHCPF-COUNTERPART'],
            ['type' => 'government', 'status' => 1]
        );

        EnrollmentPhase::unguarded(function () use ($benefactor): void {
            EnrollmentPhase::updateOrCreate(
                ['id' => 7],
                [
                    'legacy_id' => 7,
                    'name' => 'Phase Seven',
                    'phase' => 'Phase 2',
                    'sponsor' => 'BHCPF-COUNTERPART',
                    'funding' => 'cf',
                    'benefactor_id' => $benefactor->id,
                    'is_current' => true,
                    'status' => 1,
                ]
            );
        });

        $this->migrateLegacyInformal([
            'id' => 701,
            'enrolment_number' => 'PHA-001',
            'first_name' => 'Hadiza',
            'surname' => 'Umar',
            'tracking' => '7',
        ]);

        $enrollee = Enrollee::where('enrollee_id', 'PHA-001')->firstOrFail();

        $this->assertSame(7, $enrollee->enrollment_phase_id);
    }

    public function test_duplicate_enrolment_number_with_same_legacy_id_updates_existing_enrollee(): void
    {
        $facility = $this->seedGeography();
        Enrollee::create([
            'enrollee_id' => 'DUP-001',
            'legacy_enrollee_id' => 'DUP-001',
            'legacy_id' => 401,
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'facility_id' => $facility->id,
            'lga_id' => $facility->lga_id,
            'ward_id' => $facility->ward_id,
            'created_by' => User::first()->id,
            'status' => 1,
        ]);
        $this->migrateLegacyInformal([
            'id' => 401,
            'enrolment_number' => 'DUP-001',
            'first_name' => 'Updated',
            'surname' => 'Person',
        ]);

        $this->assertSame(1, Enrollee::where('enrollee_id', 'DUP-001')->count());
        $this->assertSame('Updated', Enrollee::where('enrollee_id', 'DUP-001')->firstOrFail()->first_name);
        $this->assertSame(1, LegacyMigrationLog::where('source_table', 'tbl_enrolee')->where('legacy_id', 401)->count());
    }

    public function test_duplicate_enrolment_number_with_different_legacy_id_generates_new_enrollee_id(): void
    {
        $facility = $this->seedGeography();
        Enrollee::create([
            'enrollee_id' => 'DUP-002',
            'legacy_enrollee_id' => 'DUP-002',
            'legacy_id' => 900,
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'facility_id' => $facility->id,
            'lga_id' => $facility->lga_id,
            'ward_id' => $facility->ward_id,
            'created_by' => User::first()->id,
            'status' => 1,
        ]);
        $this->migrateLegacyInformal([
            'id' => 402,
            'enrolment_number' => 'DUP-002',
            'first_name' => 'New',
            'surname' => 'Person',
        ]);

        $this->assertSame(2, Enrollee::where('legacy_enrollee_id', 'DUP-002')->count());
        $this->assertDatabaseHas('enrollees', [
            'legacy_id' => 402,
            'legacy_enrollee_id' => 'DUP-002',
            'enrollee_id' => 'LEG-I-402',
        ]);
    }

    public function test_running_command_twice_is_safe(): void
    {
        $legacy = $this->legacyRow([
            'id' => 501,
            'pin' => 'PINSAFE',
            'enrolment_number' => 'SAFE-001',
            'first_name' => 'Idris',
            'surname' => 'Sani',
            'mode_of_enrolment' => 'Premium PIN',
        ]);

        $this->migrationService()->migrate($legacy, 'tbl_enrolee');
        $this->migrationService()->migrate($legacy, 'tbl_enrolee');

        $enrollee = Enrollee::where('enrollee_id', 'SAFE-001')->firstOrFail();

        $this->assertSame(1, Enrollee::where('enrollee_id', 'SAFE-001')->count());
        $this->assertTrue($enrollee->isActive());
        $this->assertNotNull($enrollee->coverage_start_date);
        $this->assertSame(1, PremiumPurchase::where('payment_reference', 'LEGACY-TBL_ENROLEE-501-PINSAFE')->count());
    }

    public function test_failed_rows_are_logged(): void
    {
        $legacy = $this->legacyRow([
            'id' => 601,
            'enrolment_number' => 'FAIL-001',
            'first_name' => 'Faulty',
            'surname' => 'Record',
        ]);

        $this->migrationService()->logFailure($legacy, 'tbl_enrolee', new RuntimeException('forced failure'));

        $this->assertDatabaseHas('legacy_migration_logs', [
            'source_table' => 'tbl_enrolee',
            'legacy_id' => 601,
            'migration_status' => 'failed',
            'message' => 'forced failure',
        ]);
    }

    private function migrateLegacyInformal(array $overrides): array
    {
        return $this->migrationService()->migrate($this->legacyRow($overrides), 'tbl_enrolee');
    }

    private function migrateLegacyFormal(array $overrides): array
    {
        return $this->migrationService()->migrate($this->legacyRow($overrides), 'tbl_enrolee_formal');
    }

    private function legacyRow(array $overrides): object
    {
        return (object) array_merge($this->legacyDefaults(), $overrides);
    }

    private function migrationService(): LegacyEnrolleeMigrationService
    {
        return app(LegacyEnrolleeMigrationService::class);
    }

    private function seedLegacyCategoryBuckets(): void
    {
        foreach ([
            'formal_sector' => 'Formal',
            'informal_sector' => 'Informal',
            'vulnerable_groups' => 'Vulnerable',
        ] as $programmeCode => $categoryName) {
            $programme = InsuranceProgramme::where('code', $programmeCode)->firstOrFail();
            EnrolleeCategory::firstOrCreate(
                [
                    'insurance_programme_id' => $programme->id,
                    'code' => str($categoryName)->lower()->slug('_')->toString(),
                ],
                [
                    'name' => $categoryName,
                    'status' => 'active',
                ]
            );
        }
    }

    private function legacyDefaults(): array
    {
        return [
            'id' => 1,
            'pin' => null,
            'enrolee_category' => 'principal',
            'principal_id' => null,
            'surname' => 'Legacy',
            'first_name' => 'Enrollee',
            'other_name' => null,
            'enrolment_number' => 'LEG-001',
            'mode_of_enrolment' => 'general',
            'enrol_date' => '2026-01-01',
            'cap_date_month' => '2026-01-01',
            'marital_status' => 'single',
            'email_address' => null,
            'date_of_birth' => '1990-01-01',
            'vulnerability_status' => null,
            'disability' => null,
            'pregnant' => 0,
            'BHCPF_number' => null,
            'nin' => null,
            'national_identification_number' => null,
            'village' => null,
            'phone_number' => '08030000000',
            'occupation' => null,
            'address' => 'Legacy address',
            'community' => null,
            'provider_id' => null,
            'p_provider_id' => null,
            'enrolee_image_link' => null,
            'sex' => 'male',
            'nok_name' => null,
            'nok_phone_number' => null,
            'nok_address' => null,
            'nok_relationship' => null,
            'employer' => null,
            'ministry' => null,
            'cno' => null,
            'station' => null,
            'date_of_first_appointment' => null,
            'date_of_retirement' => null,
            'lga' => null,
            'ward' => null,
            'benefit_plan' => null,
            'enrolment_approval_status' => '1',
            'approved_date' => '2026-01-01 00:00:00',
            'status' => '1',
            'date_expired' => '2026-12-31',
            'funding' => null,
            'benefactor' => null,
            'basic_salary' => null,
            'salary_scheme' => null,
            'tracking' => '5',
        ];
    }

    private function seedGeography(string $facilityCode = 'TEST-HCP'): Facility
    {
        $lga = Lga::firstOrCreate(['code' => 'TST'], ['name' => 'Test LGA', 'zone' => 1, 'status' => 1]);
        $ward = Ward::firstOrCreate(['name' => 'Test Ward', 'lga_id' => $lga->id], ['settlement_type' => 1, 'status' => 1]);

        return Facility::firstOrCreate(
            ['hcp_code' => $facilityCode],
            ['name' => 'Test Facility', 'ownership' => 'Public', 'type' => 'Primary', 'lga_id' => $lga->id, 'ward_id' => $ward->id, 'status' => 1]
        );
    }
}
