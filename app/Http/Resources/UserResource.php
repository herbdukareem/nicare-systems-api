<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class UserResource
 *
 * Transforms User model for JSON responses.
 */
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'username' => $this->username,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'userable_type' => $this->userable_type,
            'userable_id' => $this->userable_id,
            'userable' => $this->whenLoaded('userable'),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'audit_trails' => AuditTrailResource::collection($this->whenLoaded('auditTrails')),
            'last_login_at' => $this->last_login_at,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(): string
    {
        return match($this->status) {
            0 => 'Pending',
            1 => 'Active',
            2 => 'Suspended',
            default => 'Unknown'
        };
    }
}
