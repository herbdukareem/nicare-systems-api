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
        // Call the AdminUserSeeder to create the super admin user
        $this->call(AdminUserSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(FacilitySeeder::class);
        $this->call(ServiceCategorySeeder::class);
        $this->call(CaseCategorySeeder::class);
        $this->call(CaseTypeSeeder::class);
        $this->call(ServiceTypeSeeder::class);

        // Create test desk officer for testing
        $this->call(TestDeskOfficerSeeder::class);

        // Claims Automation bundles and test data
        $this->call(ClaimsAutomationSeeder::class);

        // Document requirements for referral and PA code requests
        $this->call(DocumentRequirementSeeder::class);
    }
}
