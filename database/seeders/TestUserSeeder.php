<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test admin user
        User::updateOrCreate(
            ['email' => 'admin@ngscha.test'],
            [
                'name' => 'Test Admin',
                'username' => 'admin',
                'email' => 'admin@ngscha.test',
                'password' => Hash::make('password'),
                'phone' => '+2348000000000',
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );

        echo "Test user created:\n";
        echo "Username: admin\n";
        echo "Email: admin@ngscha.test\n";
        echo "Password: password\n";
    }
}
