<?php

namespace App\Services;

use App\Models\AuditTrail;
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
            'user_id' => auth()->id() ?? 1,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
