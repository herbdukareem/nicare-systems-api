<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class PremiumAuditService
{
    public function __construct(
        private SystemAuditUserResolver $systemAuditUserResolver
    ) {
    }

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
        $user = auth()->user();
        if ($user instanceof User) {
            return (int) $user->id;
        }

        // Enrollee portal identities are stored in the enrollees table, while
        // audit_trails.user_id is intentionally constrained to staff users.
        // Attribute portal-initiated changes to the system audit actor instead.
        return $this->systemAuditUserResolver->resolveId();
    }
}
