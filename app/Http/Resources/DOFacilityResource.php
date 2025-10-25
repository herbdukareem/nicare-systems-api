<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class DOFacilityResource
 *
 * Transforms DOFacility model for JSON responses.
 */
class DOFacilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'facility_id' => $this->facility_id,
            'assigned_at' => $this->assigned_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'username' => $this->user->username,
                'roles' => $this->user->roles->pluck('name'),
            ],
            'facility' => [
                'id' => $this->facility->id,
                'hcp_code' => $this->facility->hcp_code,
                'name' => $this->facility->name,
                'ownership' => $this->facility->ownership,
                'level_of_care' => $this->facility->level_of_care,
                'address' => $this->facility->address,
                'phone' => $this->facility->phone,
                'email' => $this->facility->email,
                'lga' => $this->whenLoaded('facility.lga', function () {
                    return [
                        'id' => $this->facility->lga->id,
                        'name' => $this->facility->lga->name,
                    ];
                }),
                'ward' => $this->whenLoaded('facility.ward', function () {
                    return [
                        'id' => $this->facility->ward->id,
                        'name' => $this->facility->ward->name,
                    ];
                }),
                'status' => $this->facility->status,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
