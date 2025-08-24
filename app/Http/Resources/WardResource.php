<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class WardResource
 *
 * Transforms Ward model for JSON responses.
 */
class WardResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'enrollment_cap' => $this->enrollment_cap,
            'total_enrolled' => $this->total_enrolled,
            'settlement_type' => $this->settlement_type,
            'status' => $this->status,
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
