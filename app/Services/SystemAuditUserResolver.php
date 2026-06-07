<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SystemAuditUserResolver
{
    public function resolveId(): int
    {
        $systemUser = User::where('username', 'system.audit')->first();

        if ($systemUser) {
            return (int) $systemUser->id;
        }

        $staff = Staff::firstOrCreate(
            ['email' => 'system.audit@local.test'],
            [
                'first_name' => 'System',
                'last_name' => 'Audit',
                'phone' => null,
                'status' => 1,
            ]
        );

        $systemUser = User::create([
            'name' => 'System Audit',
            'username' => 'system.audit',
            'email' => 'system.audit@local.test',
            'password' => Hash::make(bin2hex(random_bytes(16))),
            'status' => 1,
            'userable_type' => Staff::class,
            'userable_id' => $staff->id,
        ]);

        return (int) $systemUser->id;
    }
}
