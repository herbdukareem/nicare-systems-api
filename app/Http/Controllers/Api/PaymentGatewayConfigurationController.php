<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Billing\PaymentGatewayConfigurationService;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'active_gateway' => ['required', 'string', 'in:paystack,bank_transfer,pos,cash'],
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
        ]);

        return $this->sendResponse(
            $this->service->save($validated),
            'Payment gateway configuration saved successfully.'
        );
    }
}
