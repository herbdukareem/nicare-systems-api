<?php

namespace App\Services;

use App\Models\AuditTrail;
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
        if (auth()->id()) {
            return (int) auth()->id();
        }

        return $this->systemAuditUserResolver->resolveId();
    }
}
