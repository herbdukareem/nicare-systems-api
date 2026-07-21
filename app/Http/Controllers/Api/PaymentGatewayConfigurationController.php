<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Billing\PaymentGatewayConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentGatewayConfigurationController extends BaseController
{
    public function __construct(private PaymentGatewayConfigurationService $service)
    {
    }

    public function show()
    {
        return $this->sendResponse(
            $this->service->getAll(),
            'Payment gateway configuration retrieved successfully.'
        );
    }

    public function update(Request $request)
    {
        $gatewayCodes = $this->service->availableGatewayCodes();

        $validated = $request->validate([
            'active_gateway' => ['required', 'string', Rule::in($gatewayCodes)],
            'gateway_configurations' => ['required', 'array'],
            'gateway_configurations.paystack.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.paystack.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.paystack.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.public_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.paystack.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.paystack.response_paths' => ['nullable', 'array'],
            'gateway_configurations.paystack.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.monnify.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.monnify.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.monnify.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.login_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.api_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.contract_code' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.monnify.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.monnify.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.payment_methods' => ['nullable', 'array'],
            'gateway_configurations.monnify.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.monnify.response_paths' => ['nullable', 'array'],
            'gateway_configurations.monnify.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.remita.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.remita.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.remita.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.remita.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.remita.response_paths' => ['nullable', 'array'],
            'gateway_configurations.remita.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.quickteller.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.quickteller.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.merchant_code' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.pay_item_id' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.quickteller.mode' => ['nullable', 'string', Rule::in(['TEST', 'LIVE'])],
            'gateway_configurations.quickteller.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.quickteller.response_paths' => ['nullable', 'array'],
            'gateway_configurations.quickteller.successful_payment_values' => ['nullable', 'array'],
            'subaccounts' => ['nullable', 'array'],
            'subaccounts.*.code' => ['required_with:subaccounts', 'string', 'max:80'],
            'subaccounts.*.gateway_code' => ['required_with:subaccounts', 'string', Rule::in($gatewayCodes)],
            'subaccounts.*.name' => ['nullable', 'string', 'max:120'],
            'subaccounts.*.external_code' => ['nullable', 'string', 'max:120'],
            'subaccounts.*.currency' => ['nullable', 'string', 'max:10'],
            'subaccounts.*.account_name' => ['nullable', 'string', 'max:120'],
            'subaccounts.*.bank_code' => ['nullable', 'string', 'max:20'],
            'subaccounts.*.account_number' => ['nullable', 'string', 'max:40'],
            'subaccounts.*.email' => ['nullable', 'email', 'max:120'],
            'subaccounts.*.active' => ['nullable', 'boolean'],
            'split_profiles' => ['nullable', 'array'],
            'split_profiles.*.code' => ['required_with:split_profiles', 'string', 'max:80'],
            'split_profiles.*.name' => ['nullable', 'string', 'max:120'],
            'split_profiles.*.gateway_code' => ['required_with:split_profiles', 'string', Rule::in($gatewayCodes)],
            'split_profiles.*.active' => ['nullable', 'boolean'],
            'split_profiles.*.settings' => ['nullable', 'array'],
            'split_profiles.*.entries' => ['nullable', 'array'],
            'split_profiles.*.entries.*.subaccount_code' => ['required_with:split_profiles.*.entries', 'string', 'max:80'],
            'split_profiles.*.entries.*.share_type' => ['required_with:split_profiles.*.entries', 'string', 'in:percentage,flat'],
            'split_profiles.*.entries.*.share_value' => ['required_with:split_profiles.*.entries', 'numeric', 'gt:0'],
            'split_profiles.*.entries.*.fee_bearer' => ['nullable', 'boolean'],
            'split_profiles.*.entries.*.fee_percentage' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->sendResponse(
            $this->service->save($validated),
            'Payment gateway configuration saved successfully.'
        );
    }
}
