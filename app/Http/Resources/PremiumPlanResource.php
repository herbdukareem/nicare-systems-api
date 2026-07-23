<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PremiumPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_programme_id' => $this->insurance_programme_id,
            'benefit_package_id' => $this->benefit_package_id,
            'funding_type_id' => $this->funding_type_id,
            'name' => $this->name,
            'code' => $this->code,
            'amount' => $this->amount,
            'consultant_fee' => $this->consultant_fee,
            'duration_days' => $this->duration_days,
            'has_no_expiry' => (bool) $this->has_no_expiry,
            'duration_label' => $this->has_no_expiry ? 'No Expiry' : (($this->duration_days ?? 0) . ' days'),
            'waiting_period_days' => $this->waiting_period_days,
            'is_family_plan' => (bool) $this->is_family_plan,
            'maximum_dependants' => $this->maximum_dependants,
            'effective_maximum_dependants' => $this->getEffectiveMaximumDependants(),
            'payment_required' => (bool) $this->payment_required,
            'self_enrollment_enabled' => (bool) $this->self_enrollment_enabled,
            'payment_gateway' => $this->payment_gateway,
            'merchant_id' => $this->merchant_id,
            'merchant_service_type_id' => $this->merchant_service_type_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'programme' => $this->whenLoaded('programme'),
            'benefit_package' => $this->whenLoaded('benefitPackage'),
            'funding_type' => $this->whenLoaded('fundingType'),
            'merchant' => $this->merchantDetails(),
            'merchant_service_type' => $this->merchantServiceTypeDetails(),
        ];
    }

    private function merchantDetails(): ?object
    {
        if (!$this->merchant_id || !Schema::hasTable('merchants')) {
            return null;
        }

        return DB::table('merchants')->where('id', $this->merchant_id)->first();
    }

    private function merchantServiceTypeDetails(): ?object
    {
        if (!$this->merchant_service_type_id || !Schema::hasTable('merchant_service_types')) {
            return null;
        }

        return DB::table('merchant_service_types')->where('id', $this->merchant_service_type_id)->first();
    }
}
