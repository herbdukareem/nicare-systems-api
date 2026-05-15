<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(FacilitySeeder::class);
        $this->call(CaseTypeSeeder::class);

        // Create test desk officer for testing
        $this->call(TestDeskOfficerSeeder::class);

        // Document requirements for referral and PA code requests
        $this->call(DocumentRequirementSeeder::class);
        $this->call(InsuranceProgrammeSeeder::class);

        // --class=CaseRecordsWithDetailsSeeder
        $this->call(CaseRecordsWithDetailsSeeder::class);
    }
}
