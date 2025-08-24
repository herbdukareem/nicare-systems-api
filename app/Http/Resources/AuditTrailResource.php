<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class AuditTrailResource
 *
 * Transforms AuditTrail model for JSON responses.
 */
class AuditTrailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'enrollee_id' => $this->enrollee_id,
            'action' => $this->action,
            'description' => $this->description,
            'user' => new UserResource($this->whenLoaded('user')),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
