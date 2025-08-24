<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class PremiumResource
 *
 * Transforms Premium model for JSON responses.
 */
class PremiumResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'pin' => $this->pin,
            'serial_no' => $this->serial_no,
            'pin_type' => $this->pin_type,
            'pin_category' => $this->pin_category,
            'benefit_type' => $this->benefit_type,
            'amount' => $this->amount,
            'date_generated' => $this->date_generated,
            'date_used' => $this->date_used,
            'date_expired' => $this->date_expired,
            'status' => $this->status,
            'used_by' => new UserResource($this->whenLoaded('usedBy')),
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
