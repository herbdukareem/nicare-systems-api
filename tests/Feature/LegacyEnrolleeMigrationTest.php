<?php

namespace Tests\Feature;

use App\Models\CoveragePeriod;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\LegacyMigrationLog;
use App\Models\Lga;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Models\Ward;
use App\Services\Legacy\LegacyEnrolleeMigrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LegacyEnrolleeMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy_mysql' => array_merge(
            config('database.connections.mysql'),
            ['database' => config('database.connections.mysql.database')]
        )]);

        DB::purge('legacy_mysql');
        DB::reconnect('legacy_mysql');

        $this->createLegacyTables();
    }

    protected function tearDown(): void
    {
        Schema::connection('legacy_mysql')->dropIfExists('tbl_enrolee');
        Schema::connection('legacy_mysql')->dropIfExists('tbl_enrolee_formal');
        Schema::connection('legacy_mysql')->dropIfExists('tbl_providers');

        parent::tearDown();
    }

    public function test_formal_enrollee_migration_creates_active_coverage(): void
    {
        $this->seedLegacyProvider();
        $this->insertLegacyFormal([
            'id' => 101,
            'enrolment_number' => 'FORM-001',
            'first_name' => 'Amina',
            'surname' => 'Bala',
            'funding' => 'payroll',
            'employer' => 'Niger State Civil Service',
            'basic_salary' => 100000,
        ]);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'formal'])
            ->assertSuccessful();

        $enrollee = Enrollee::where('enrollee_id', 'FORM-001')->firstOrFail();
        $coverage = CoveragePeriod::where('enrollee_id', $enrollee->id)->firstOrFail();

        $this->assertSame(CoveragePeriod::STATUS_ACTIVE, $coverage->status);
        $this->assertSame('payroll', $coverage->activation_source);
        $this->assertSame('Payroll Deduction', $coverage->fundingType->name);
        $this->assertDatabaseHas('premium_purchases', [
            'payment_reference' => 'LEGACY-FORMAL-101-NO-PIN',
            'payment_status' => 'confirmed',
        ]);
    }

    public function test_bhcpf_enrollee_creates_vulnerable_coverage_with_bhcpf_funding(): void
    {
        $this->insertLegacyInformal([
            'id' => 201,
            'enrolment_number' => 'BHC-001',
            'first_name' => 'Musa',
            'surname' => 'Ibrahim',
            'mode_of_enrolment' => 'BHCPF',
            'funding' => 'BHCPF',
            'BHCPF_number' => 'BHCPF-001',
        ]);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertSuccessful();

        $coverage = CoveragePeriod::whereHas('enrollee', fn ($query) => $query->where('enrollee_id', 'BHC-001'))->firstOrFail();

        $this->assertSame(CoveragePeriod::STATUS_ACTIVE, $coverage->status);
        $this->assertSame('subsidy', $coverage->activation_source);
        $this->assertSame('BHCPF', $coverage->fundingType->name);
        $this->assertSame('Vulnerable Group Social Health Insurance', $coverage->programme->name);
        $this->assertSame(0, PremiumPurchase::count());
    }

    public function test_pin_enrollee_creates_informal_coverage_and_purchase(): void
    {
        $this->insertLegacyInformal([
            'id' => 301,
            'pin' => 'PIN12345',
            'enrolment_number' => 'PIN-001',
            'first_name' => 'Zainab',
            'surname' => 'Garba',
            'mode_of_enrolment' => 'Premium PIN',
            'funding' => 'premium',
        ]);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertSuccessful();

        $coverage = CoveragePeriod::whereHas('enrollee', fn ($query) => $query->where('enrollee_id', 'PIN-001'))->firstOrFail();

        $this->assertSame('payment', $coverage->activation_source);
        $this->assertSame('Out-of-pocket', $coverage->fundingType->name);
        $this->assertSame('Informal Sector Programme', $coverage->programme->name);
        $this->assertDatabaseHas('premium_purchases', [
            'payment_reference' => 'LEGACY-TBL_ENROLEE-301-PIN12345',
            'payment_status' => 'confirmed',
        ]);
    }

    public function test_duplicate_enrollee_is_not_recreated(): void
    {
        $facility = $this->seedGeography();
        User::factory()->create();
        Enrollee::create([
            'enrollee_id' => 'DUP-001',
            'legacy_enrollee_id' => 'DUP-001',
            'first_name' => 'Existing',
            'last_name' => 'Person',
            'facility_id' => $facility->id,
            'lga_id' => $facility->lga_id,
            'ward_id' => $facility->ward_id,
            'created_by' => User::first()->id,
            'status' => 1,
        ]);
        $this->insertLegacyInformal([
            'id' => 401,
            'enrolment_number' => 'DUP-001',
            'first_name' => 'Updated',
            'surname' => 'Person',
        ]);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertSuccessful();

        $this->assertSame(1, Enrollee::where('enrollee_id', 'DUP-001')->count());
        $this->assertSame(1, LegacyMigrationLog::where('source_table', 'tbl_enrolee')->where('legacy_id', 401)->count());
    }

    public function test_running_command_twice_is_safe(): void
    {
        $this->insertLegacyInformal([
            'id' => 501,
            'pin' => 'PINSAFE',
            'enrolment_number' => 'SAFE-001',
            'first_name' => 'Idris',
            'surname' => 'Sani',
            'mode_of_enrolment' => 'Premium PIN',
        ]);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertSuccessful();
        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertSuccessful();

        $enrollee = Enrollee::where('enrollee_id', 'SAFE-001')->firstOrFail();

        $this->assertSame(1, Enrollee::where('enrollee_id', 'SAFE-001')->count());
        $this->assertSame(1, CoveragePeriod::where('enrollee_id', $enrollee->id)->count());
        $this->assertSame(1, PremiumPurchase::where('payment_reference', 'LEGACY-TBL_ENROLEE-501-PINSAFE')->count());
    }

    public function test_failed_rows_are_logged(): void
    {
        $this->insertLegacyInformal([
            'id' => 601,
            'enrolment_number' => 'FAIL-001',
            'first_name' => 'Faulty',
            'surname' => 'Record',
        ]);

        $mock = Mockery::mock(LegacyEnrolleeMigrationService::class);
        $mock->shouldReceive('migrate')->once()->andThrow(new RuntimeException('forced failure'));
        $mock->shouldReceive('logFailure')->once()->andReturnUsing(function (object $legacy, string $sourceTable, RuntimeException $exception): void {
            LegacyMigrationLog::create([
                'source_table' => $sourceTable,
                'legacy_id' => (int) $legacy->id,
                'legacy_enrolment_number' => $legacy->enrolment_number,
                'migration_status' => 'failed',
                'message' => $exception->getMessage(),
                'legacy_payload' => json_decode(json_encode($legacy), true),
            ]);
        });
        $this->app->instance(LegacyEnrolleeMigrationService::class, $mock);

        $this->artisan('legacy:migrate-enrollees', ['--source' => 'informal'])
            ->assertFailed();

        $this->assertDatabaseHas('legacy_migration_logs', [
            'source_table' => 'tbl_enrolee',
            'legacy_id' => 601,
            'migration_status' => 'failed',
            'message' => 'forced failure',
        ]);
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_mysql')->dropIfExists('tbl_enrolee');
        Schema::connection('legacy_mysql')->dropIfExists('tbl_enrolee_formal');
        Schema::connection('legacy_mysql')->dropIfExists('tbl_providers');

        foreach (['tbl_enrolee', 'tbl_enrolee_formal'] as $table) {
            Schema::connection('legacy_mysql')->create($table, function (Blueprint $table): void {
                $table->unsignedBigInteger('id')->primary();
                $table->string('pin')->nullable();
                $table->string('enrolee_category')->nullable();
                $table->string('principal_id')->nullable();
                $table->string('surname')->nullable();
                $table->string('first_name')->nullable();
                $table->string('other_name')->nullable();
                $table->string('enrolment_number')->nullable();
                $table->string('mode_of_enrolment')->nullable();
                $table->date('enrol_date')->nullable();
                $table->date('cap_date_month')->nullable();
                $table->string('marital_status')->nullable();
                $table->string('email_address')->nullable();
                $table->string('date_of_birth')->nullable();
                $table->string('vulnerability_status')->nullable();
                $table->string('disability')->nullable();
                $table->unsignedTinyInteger('pregnant')->default(0);
                $table->string('BHCPF_number')->nullable();
                $table->string('nin')->nullable();
                $table->string('national_identification_number')->nullable();
                $table->string('village')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('occupation')->nullable();
                $table->text('address')->nullable();
                $table->string('community')->nullable();
                $table->unsignedBigInteger('provider_id')->nullable();
                $table->unsignedBigInteger('p_provider_id')->nullable();
                $table->string('enrolee_image_link')->nullable();
                $table->string('sex')->nullable();
                $table->string('nok_name')->nullable();
                $table->string('nok_phone_number')->nullable();
                $table->string('nok_address')->nullable();
                $table->string('nok_relationship')->nullable();
                $table->string('employer')->nullable();
                $table->string('ministry')->nullable();
                $table->string('cno')->nullable();
                $table->string('station')->nullable();
                $table->date('date_of_first_appointment')->nullable();
                $table->date('date_of_retirement')->nullable();
                $table->string('lga')->nullable();
                $table->string('ward')->nullable();
                $table->string('benefit_plan')->nullable();
                $table->string('enrolment_approval_status')->nullable();
                $table->dateTime('approved_date')->nullable();
                $table->string('status')->nullable();
                $table->date('date_expired')->nullable();
                $table->string('funding')->nullable();
                $table->string('benefactor')->nullable();
                $table->decimal('basic_salary', 14, 2)->nullable();
                $table->string('salary_scheme')->nullable();
            });
        }

        Schema::connection('legacy_mysql')->create('tbl_providers', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('hcpcode')->nullable();
            $table->string('hcpname')->nullable();
        });
    }

    private function insertLegacyInformal(array $overrides): void
    {
        DB::connection('legacy_mysql')->table('tbl_enrolee')->insert(array_merge($this->legacyDefaults(), $overrides));
    }

    private function insertLegacyFormal(array $overrides): void
    {
        DB::connection('legacy_mysql')->table('tbl_enrolee_formal')->insert(array_merge($this->legacyDefaults(), $overrides));
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
            'provider_id' => 1,
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
        ];
    }

    private function seedLegacyProvider(): Facility
    {
        $facility = $this->seedGeography('HCP-001');
        DB::connection('legacy_mysql')->table('tbl_providers')->insert([
            'id' => 1,
            'hcpcode' => 'HCP-001',
            'hcpname' => 'Legacy Provider',
        ]);

        return $facility;
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
