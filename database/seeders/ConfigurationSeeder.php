<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configurations = [
            [
                'variable_name' => 'DRUG_MARKUP',
                'variable_value' => '13%',
                'description' => 'Markup percentage for drug pricing from external API'
            ],
            [
                'variable_name' => 'SYSTEM_NAME',
                'variable_value' => 'NiCare Systems',
                'description' => 'System name for branding and display'
            ],
            [
                'variable_name' => 'CURRENCY_SYMBOL',
                'variable_value' => '₦',
                'description' => 'Currency symbol for pricing display'
            ],
            [
                'variable_name' => 'DEFAULT_PAGINATION_SIZE',
                'variable_value' => '15',
                'description' => 'Default number of items per page for pagination'
            ]
        ];

        foreach ($configurations as $config) {
            Configuration::updateOrCreate(
                ['variable_name' => $config['variable_name']],
                $config
            );
        }
    }
}
