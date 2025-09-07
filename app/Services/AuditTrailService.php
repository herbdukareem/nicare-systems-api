<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling audit trail operations.
 */
class AuditTrailService
{
    public function all(): Collection
    {
        return AuditTrail::with(['enrollee', 'user'])->get();
    }

    public function create(array $data): AuditTrail
    {
        return AuditTrail::create($data);
    }

    public function update(AuditTrail $auditTrail, array $data): AuditTrail
    {
        $auditTrail->update($data);
        return $auditTrail;
    }

    public function delete(AuditTrail $auditTrail): void
    {
        $auditTrail->delete();
    }
}
