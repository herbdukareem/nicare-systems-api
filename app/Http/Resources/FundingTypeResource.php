<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class FundingTypeResource
 *
 * Transforms FundingType model for JSON responses.
 */
class FundingTypeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'enrollees_count' => $this->whenCounted('enrollees'),
            'payroll_batches_count' => $this->whenCounted('payrollBatches'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
