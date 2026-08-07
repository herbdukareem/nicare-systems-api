<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Billing\PaymentCollectionConfigurationService;
use Illuminate\Http\Request;

class PaymentCollectionConfigurationController extends BaseController
{
    public function __construct(private PaymentCollectionConfigurationService $settings) {}
    public function show() { return $this->sendResponse($this->settings->get(), 'Payment collection configuration retrieved successfully.'); }
    public function update(Request $request) {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'provider' => ['required', 'in:paystack'], 'default_mode' => ['required', 'in:per_payment,per_payer'], 'allow_modes' => ['required', 'array', 'min:1'], 'allow_modes.*' => ['in:per_payment,per_payer'], 'exact_amount_only' => ['required', 'boolean'], 'refunds_allowed' => ['required', 'boolean'], 'per_payment.expiry_minutes' => ['required', 'integer', 'min:15', 'max:480'], 'per_payer.intent_expiry_hours' => ['required', 'integer', 'min:1', 'max:168'], 'bulk_pin.intent_expiry_hours' => ['required', 'integer', 'min:24', 'max:168']]);
        return $this->sendResponse($this->settings->save($data), 'Payment collection configuration saved successfully.');
    }
}
