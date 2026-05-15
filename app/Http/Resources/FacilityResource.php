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
            'ownership' => $this->ownership,
            'category' => $this->ownership ?? $this->category,
            'type' => $this->type,
            'level_of_care' => $this->level_of_care,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'capacity' => $this->capacity,
            'current_enrollees_count' => $this->facility_capacity,
            'enrollees_count' => $this->whenCounted('enrollees'),
            'status' => $this->status,
            'accreditation_status' => $this->accreditation_status ?? 'active',
            'account_detail' => new AccountDetailResource($this->whenLoaded('accountDetail')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
