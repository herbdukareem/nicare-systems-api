<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BenefitPackageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'premium_plans_count' => $this->whenCounted('premiumPlans'),
            'enrollees_count' => $this->whenCounted('enrollees'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
