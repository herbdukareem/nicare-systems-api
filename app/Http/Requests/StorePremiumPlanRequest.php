<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePremiumPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('premiumPlan')?->id ?? $this->route('premium_plan') ?? null;
        $rules = [
            'insurance_programme_id' => ['required', 'exists:insurance_programmes,id'],
            'benefit_package_id' => ['nullable', 'exists:benefit_packages,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('premium_plans', 'code')->ignore($planId)],
            'amount' => ['required', 'numeric', 'min:0'],
            'consultant_fee' => ['required', 'numeric', 'min:0'],
            'payment_required' => ['nullable', 'boolean'],
            'self_enrollment_enabled' => ['nullable', 'boolean'],
            'payment_gateway' => ['nullable', 'string', 'max:80', Rule::in($this->supportedPaymentGateways())],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'has_no_expiry' => ['nullable', 'boolean'],
            'waiting_period_days' => ['nullable', 'integer', 'min:0'],
            'is_family_plan' => ['nullable', 'boolean'],
            'maximum_dependants' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,archived'],
        ];

        if (Schema::hasTable('merchants') && Schema::hasColumn('premium_plans', 'merchant_id')) {
            $rules['merchant_id'] = ['nullable', 'exists:merchants,id'];
        }

        if (Schema::hasTable('merchant_service_types') && Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
            $rules['merchant_service_type_id'] = ['nullable', 'exists:merchant_service_types,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isFamilyPlan = $this->boolean('is_family_plan');
            $hasNoExpiry = $this->boolean('has_no_expiry');
            $paymentRequired = $this->boolean('payment_required');

            if (!$isFamilyPlan && (int) $this->input('maximum_dependants', 0) > 0) {
                $validator->errors()->add('maximum_dependants', 'Maximum dependants must be 0 when the plan is not a family plan.');
            }

            if ($hasNoExpiry && $this->filled('duration_days')) {
                $validator->errors()->add('duration_days', 'Duration days must be empty when no expiry is selected.');
            }

            if (!$hasNoExpiry && !$this->filled('duration_days')) {
                $validator->errors()->add('duration_days', 'Duration days is required unless no expiry is selected.');
            }

            if ($paymentRequired && !$this->filled('payment_gateway')) {
                $validator->errors()->add('payment_gateway', 'Payment gateway is required when payment is required.');
            }

        });
    }

    public function normalized(): array
    {
        $data = $this->validated();
        $data['is_family_plan'] = (bool) ($data['is_family_plan'] ?? false);
        $data['has_no_expiry'] = (bool) ($data['has_no_expiry'] ?? false);
        $data['payment_required'] = (bool) ($data['payment_required'] ?? false);
        $data['self_enrollment_enabled'] = (bool) ($data['self_enrollment_enabled'] ?? false);
        $data['waiting_period_days'] = (int) ($data['waiting_period_days'] ?? 0);
        $data['consultant_fee'] = $data['consultant_fee'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';

        if (!$data['is_family_plan']) {
            $data['maximum_dependants'] = 0;
        }

        if ($data['has_no_expiry']) {
            $data['duration_days'] = null;
        }

        if (!$data['payment_required']) {
            $data['payment_gateway'] = null;
            if (Schema::hasColumn('premium_plans', 'merchant_id')) {
                $data['merchant_id'] = null;
            }
            if (Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
                $data['merchant_service_type_id'] = null;
            }
        }

        if (!Schema::hasColumn('premium_plans', 'merchant_id')) {
            unset($data['merchant_id']);
        }

        if (!Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
            unset($data['merchant_service_type_id']);
        }

        return [
            ...$data,
            'maximum_dependants' => (int) ($data['maximum_dependants'] ?? 0),
        ];
    }

    private function supportedPaymentGateways(): array
    {
        return ['remita', 'paystack', 'flutterwave', 'xpresspay', 'bank_transfer', 'pos', 'cash'];
    }

}
