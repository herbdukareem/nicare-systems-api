<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Services\Billing\PaymentGatewayConfigurationService;

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
            'bank_transfer_enabled' => ['nullable', 'boolean'],
            'bank_transfer_bank_name' => ['nullable', 'string', 'max:255'],
            'bank_transfer_account_name' => ['nullable', 'string', 'max:255'],
            'bank_transfer_account_number' => ['nullable', 'string', 'max:50'],
            'bank_transfer_instructions' => ['nullable', 'string'],
            'payment_split_profile_code' => ['nullable', 'string', 'max:80'],
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
            $bankTransferEnabled = $this->boolean('bank_transfer_enabled');

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

            if ($bankTransferEnabled && !$paymentRequired) {
                $validator->errors()->add('bank_transfer_enabled', 'Dedicated bank transfer can only be enabled for plans that require payment.');
            }

            if ($bankTransferEnabled) {
                foreach ([
                    'bank_transfer_bank_name' => 'Bank name is required when dedicated bank transfer is enabled.',
                    'bank_transfer_account_name' => 'Account name is required when dedicated bank transfer is enabled.',
                    'bank_transfer_account_number' => 'Account number is required when dedicated bank transfer is enabled.',
                ] as $field => $message) {
                    if (!filled(trim((string) $this->input($field, '')))) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }

            if ($paymentRequired && $this->filled('payment_split_profile_code')) {
                $service = app(PaymentGatewayConfigurationService::class);
                $profileCode = (string) $this->input('payment_split_profile_code');
                $gatewayCode = (string) $this->input('payment_gateway');
                $profile = collect($service->getSplitProfiles())->firstWhere('code', $profileCode);

                if (!$service->supportsSplitProfiles($gatewayCode)) {
                    $validator->errors()->add('payment_split_profile_code', 'The selected payment gateway does not support split settlement profiles yet.');
                } elseif (!$profile) {
                    $validator->errors()->add('payment_split_profile_code', 'Selected split profile does not exist.');
                } elseif (($profile['gateway_code'] ?? null) !== $gatewayCode) {
                    $validator->errors()->add('payment_split_profile_code', 'Selected split profile does not match the chosen payment gateway.');
                }
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
        $data['bank_transfer_enabled'] = (bool) ($data['bank_transfer_enabled'] ?? false);
        $data['waiting_period_days'] = (int) ($data['waiting_period_days'] ?? 0);
        $data['consultant_fee'] = $data['consultant_fee'] ?? 0;
        $data['status'] = $data['status'] ?? 'active';
        $data['bank_transfer_bank_name'] = $this->filled('bank_transfer_bank_name')
            ? trim((string) $data['bank_transfer_bank_name'])
            : null;
        $data['bank_transfer_account_name'] = $this->filled('bank_transfer_account_name')
            ? trim((string) $data['bank_transfer_account_name'])
            : null;
        $data['bank_transfer_account_number'] = $this->filled('bank_transfer_account_number')
            ? trim((string) $data['bank_transfer_account_number'])
            : null;
        $data['bank_transfer_instructions'] = $this->filled('bank_transfer_instructions')
            ? trim((string) $data['bank_transfer_instructions'])
            : null;

        if (!$data['is_family_plan']) {
            $data['maximum_dependants'] = 0;
        }

        if ($data['has_no_expiry']) {
            $data['duration_days'] = null;
        }

        if (!$data['payment_required']) {
            $data['payment_gateway'] = null;
            $data['bank_transfer_enabled'] = false;
            $data['bank_transfer_bank_name'] = null;
            $data['bank_transfer_account_name'] = null;
            $data['bank_transfer_account_number'] = null;
            $data['bank_transfer_instructions'] = null;
            $data['payment_split_profile_code'] = null;
            if (Schema::hasColumn('premium_plans', 'merchant_id')) {
                $data['merchant_id'] = null;
            }
            if (Schema::hasColumn('premium_plans', 'merchant_service_type_id')) {
                $data['merchant_service_type_id'] = null;
            }
        }

        if (!$data['bank_transfer_enabled']) {
            $data['bank_transfer_bank_name'] = null;
            $data['bank_transfer_account_name'] = null;
            $data['bank_transfer_account_number'] = null;
            $data['bank_transfer_instructions'] = null;
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
        return ['paystack', 'monnify', 'remita', 'quickteller'];
    }

}
