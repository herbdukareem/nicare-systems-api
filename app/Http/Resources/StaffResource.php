<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'middle_name'      => $this->middle_name,
            'date_of_birth'    => $this->date_of_birth,
            'gender'           => $this->gender,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'designation'      => new DesignationResource($this->whenLoaded('designation')),
            'department'       => new DepartmentResource($this->whenLoaded('department')),
            'address'          => $this->address,
            'status'           => $this->status,
            'account_details'  => AccountDetailResource::collection($this->whenLoaded('accountDetails')),
            'employment_details' => EmploymentDetailResource::collection($this->whenLoaded('employmentDetails')),
        ];
    }
}
