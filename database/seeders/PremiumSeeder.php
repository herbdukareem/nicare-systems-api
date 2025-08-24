<?php

namespace Database\Seeders;

use App\Models\Premium;
use App\Models\User;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class PremiumSeeder extends Seeder
{
    public function run(): void
    {
        // Create available premiums
        Premium::factory(50)->available()->create();

        // Create used premiums (need existing users, lgas, wards)
        if (User::count() > 0 && Lga::count() > 0 && Ward::count() > 0) {
            Premium::factory(30)->used()->create([
                'used_by' => User::inRandomOrder()->first()->id,
                'lga_id' => Lga::inRandomOrder()->first()->id,
                'ward_id' => Ward::inRandomOrder()->first()->id,
            ]);
        }

        // Create expired premiums
        Premium::factory(20)->expired()->create();

        // Create some specific test premiums
        $testPremiums = [
            [
                'pin_type' => 'individual',
                'pin_category' => 'formal',
                'benefit_type' => 'basic',
                'amount' => 15000,
                'status' => 'available',
            ],
            [
                'pin_type' => 'family',
                'pin_category' => 'informal',
                'benefit_type' => 'standard',
                'amount' => 25000,
                'status' => 'available',
            ],
            [
                'pin_type' => 'individual',
                'pin_category' => 'vulnerable',
                'benefit_type' => 'basic',
                'amount' => 0,
                'status' => 'available',
            ],
        ];

        foreach ($testPremiums as $premiumData) {
            $pinData = Premium::generatePin();
            Premium::create(array_merge($premiumData, [
                'pin' => $pinData['pin'],
                'pin_raw' => $pinData['pin_raw'],
                'serial_no' => Premium::generateSerialNumber(),
                'date_generated' => now(),
                'date_expired' => now()->addYear(),
                'request_id' => \Illuminate\Support\Str::uuid(),
            ]));
        }
    }
}