<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentCollectionSettlementService;
use App\Services\Billing\PaystackPaymentCollectionGateway;
use App\Services\Billing\PaymentGatewayConfigurationService;
use Illuminate\Http\Request;

class PaymentCollectionWebhookController extends Controller
{
    public function paystack(Request $request, PaystackPaymentCollectionGateway $gateway, PaymentGatewayConfigurationService $config, PaymentCollectionSettlementService $settlement)
    {
        if (!$gateway->validWebhook($request->getContent(), $request->header('x-paystack-signature'), $config->getConfig('paystack'))) abort(401, 'Invalid webhook signature.');
        $settlement->settlePaystack($request->json()->all());
        return response()->json(['ok' => true]);
    }
}
