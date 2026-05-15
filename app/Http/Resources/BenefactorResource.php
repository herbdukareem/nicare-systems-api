<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BenefactorResource
 *
 * Transforms Benefactor model for JSON responses.
 */
class BenefactorResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'registration_number' => $this->registration_number,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status,
            'enrollees_count' => $this->whenCounted('enrollees'),
            'enrollment_phases_count' => $this->whenCounted('enrollmentPhases'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
