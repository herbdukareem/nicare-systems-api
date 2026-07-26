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
            'gateway_configurations.paystack.mode' => ['nullable', 'string', Rule::in(['TEST', 'LIVE'])],
            'gateway_configurations.paystack.environments' => ['nullable', 'array'],
            'gateway_configurations.paystack.environments.test' => ['nullable', 'array'],
            'gateway_configurations.paystack.environments.live' => ['nullable', 'array'],
            'gateway_configurations.paystack.environments.*.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.public_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.paystack.environments.*.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.paystack.environments.*.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.paystack.environments.*.response_paths' => ['nullable', 'array'],
            'gateway_configurations.paystack.environments.*.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.monnify.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.monnify.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.monnify.mode' => ['nullable', 'string', Rule::in(['TEST', 'LIVE'])],
            'gateway_configurations.monnify.environments' => ['nullable', 'array'],
            'gateway_configurations.monnify.environments.test' => ['nullable', 'array'],
            'gateway_configurations.monnify.environments.live' => ['nullable', 'array'],
            'gateway_configurations.monnify.environments.*.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.login_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.api_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.contract_code' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.monnify.environments.*.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.monnify.environments.*.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.monnify.environments.*.payment_methods' => ['nullable', 'array'],
            'gateway_configurations.monnify.environments.*.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.monnify.environments.*.response_paths' => ['nullable', 'array'],
            'gateway_configurations.monnify.environments.*.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.remita.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.remita.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.remita.mode' => ['nullable', 'string', Rule::in(['TEST', 'LIVE'])],
            'gateway_configurations.remita.environments' => ['nullable', 'array'],
            'gateway_configurations.remita.environments.test' => ['nullable', 'array'],
            'gateway_configurations.remita.environments.live' => ['nullable', 'array'],
            'gateway_configurations.remita.environments.*.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.environments.*.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.environments.*.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.environments.*.secret_key' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.environments.*.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.remita.environments.*.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.remita.environments.*.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.remita.environments.*.response_paths' => ['nullable', 'array'],
            'gateway_configurations.remita.environments.*.successful_payment_values' => ['nullable', 'array'],
            'gateway_configurations.quickteller.enabled' => ['nullable', 'boolean'],
            'gateway_configurations.quickteller.provider_name' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.mode' => ['nullable', 'string', Rule::in(['TEST', 'LIVE'])],
            'gateway_configurations.quickteller.environments' => ['nullable', 'array'],
            'gateway_configurations.quickteller.environments.test' => ['nullable', 'array'],
            'gateway_configurations.quickteller.environments.live' => ['nullable', 'array'],
            'gateway_configurations.quickteller.environments.*.base_url' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.environments.*.initialize_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.environments.*.verify_endpoint' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.environments.*.merchant_code' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.environments.*.pay_item_id' => ['nullable', 'string', 'max:120'],
            'gateway_configurations.quickteller.environments.*.currency' => ['nullable', 'string', 'max:10'],
            'gateway_configurations.quickteller.environments.*.callback_path' => ['nullable', 'string', 'max:255'],
            'gateway_configurations.quickteller.environments.*.request_amount_multiplier' => ['nullable', 'integer', 'min:1'],
            'gateway_configurations.quickteller.environments.*.response_paths' => ['nullable', 'array'],
            'gateway_configurations.quickteller.environments.*.successful_payment_values' => ['nullable', 'array'],
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
