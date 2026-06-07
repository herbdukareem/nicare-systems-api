<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class PremiumAuditService
{
    public function record(Model $model, string $action, string $description, array $old = [], array $new = []): void
    {
        AuditTrail::create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'description' => $description,
            'user_id' => $this->resolveAuditUserId(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
        ]);
    }

    private function resolveAuditUserId(): int
    {
        if (auth()->id()) {
            return (int) auth()->id();
        }

        $systemUser = User::where('username', 'system.audit')->first();

        if (!$systemUser) {
            $systemUser = User::factory()->create([
                'name' => 'System Audit',
                'username' => 'system.audit',
                'email' => 'system.audit@local.test',
            ]);
        }

        return (int) $systemUser->id;
    }
}
