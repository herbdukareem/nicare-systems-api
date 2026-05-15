<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class RoleResource
 *
 * Transforms Role model for JSON responses.
 */
class RoleResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'permission_categories' => $this->whenLoaded('permissions', function () {
                return $this->permissions
                    ->pluck('category')
                    ->filter()
                    ->unique()
                    ->values();
            }),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
