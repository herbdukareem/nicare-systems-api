<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class FacilityResource
 *
 * Transforms Facility model for JSON responses.
 */
class FacilityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'hcp_code' => $this->hcp_code,
            'name' => $this->name,
            'category' => $this->category,
            'type' => $this->type,
            'level_of_care' => $this->level_of_care,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'capacity' => $this->facility_capacity,
            'status' => $this->status,
            'account_detail' => new AccountDetailResource($this->whenLoaded('accountDetail')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
