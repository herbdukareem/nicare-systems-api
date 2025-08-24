<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class VillageResource
 *
 * Transforms Village model for JSON responses.
 */
class VillageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'ward' => new WardResource($this->whenLoaded('ward')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
