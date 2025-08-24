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
            'agent_reg_number' => $this->agent_reg_number,
            'status' => $this->status,
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
