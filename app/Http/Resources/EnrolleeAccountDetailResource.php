<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class EnrolleeAccountDetailResource
 *
 * Transforms enrollee-specific account details for JSON responses.
 */
class EnrolleeAccountDetailResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'account_name' => $this->account_name,
            'bvn' => $this->bvn,
            'nin' => $this->nin,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at,
            'verification_method' => $this->verification_method,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
