<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class EnrolleeResource
 *
 * Transforms Enrollee model for JSON responses.
 */
class EnrolleeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'enrollee_id' => $this->enrollee_id,
            'nin' => $this->nin,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'address' => $this->address,
            'enrollee_type' => new EnrolleeTypeResource($this->whenLoaded('enrolleeType')),
            'enrollee_category' => $this->enrollee_category,
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'ward' => new WardResource($this->whenLoaded('ward')),
            'village' => $this->village,
            'premium' => new PremiumResource($this->whenLoaded('premium')),
            'employment_detail' => new EmploymentDetailResource($this->whenLoaded('employmentDetail')),
            'funding_type' => new FundingTypeResource($this->whenLoaded('fundingType')),
            'benefactor' => new BenefactorResource($this->whenLoaded('benefactor')),
            'capitation_start_date' => $this->capitation_start_date,
            'approval_date' => $this->approval_date,
            'status' => $this->status,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'account_detail' => new EnrolleeAccountDetailResource($this->whenLoaded('accountDetail')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
