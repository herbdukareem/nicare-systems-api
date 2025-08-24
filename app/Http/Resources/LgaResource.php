<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class LgaResource
 *
 * Transforms Lga model for JSON responses.
 */
class LgaResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'zone' => $this->zone,
            'baseline' => $this->baseline,
            'total_enrolled' => $this->total_enrolled,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
