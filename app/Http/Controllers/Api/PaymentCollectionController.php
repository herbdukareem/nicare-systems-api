<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\PremiumPurchase;
use App\Models\PaymentIntent;
use App\Services\Billing\PaymentCollectionService;
use App\Services\Billing\PaymentGatewayConfigurationService;
use App\Services\Billing\PaystackPaymentCollectionGateway;
use App\Services\PremiumCoverageService;
use Illuminate\Http\Request;

class PaymentCollectionController extends BaseController
{
    public function index(Request $request) { return $this->sendResponse(PaymentIntent::with('accounts')->latest()->paginate($request->integer('per_page', 20)), 'Payment collections retrieved successfully.'); }
    public function createForPurchase(Request $request, PremiumPurchase $premiumPurchase, PaymentCollectionService $service)
    {
        $data = $request->validate(['mode' => ['nullable', 'in:per_payment,per_payer']]);
        $payer = ['name' => $premiumPurchase->payer_name, 'email' => $premiumPurchase->payer_email, 'phone' => $premiumPurchase->payer_phone];
        $collection = $service->createForPurchase($premiumPurchase, $data['mode'] ?? 'per_payment', data_get($premiumPurchase->payer_details, 'channel') === 'enrollee_portal_renewal' ? 'coverage_renewal' : 'premium_pin_purchase', $payer);
        return $this->sendResponse($collection, 'Virtual account collection created successfully.', 201);
    }
    public function manuallySettle(Request $request, PaymentIntent $paymentIntent, PremiumCoverageService $coverage) {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        if ($paymentIntent->status === 'paid') return $this->sendResponse($paymentIntent, 'Payment intent was already settled.');
        $paymentIntent->update([
            'status' => 'paid', 'amount_received' => $paymentIntent->amount_due, 'paid_at' => now(),
            'metadata' => array_merge($paymentIntent->metadata ?? [], ['manual_settlement_reason' => $data['reason'], 'manual_settlement_by' => $request->user()->id]),
        ]);
        if (($purchase = $paymentIntent->payable) instanceof PremiumPurchase) $coverage->confirmPurchase($purchase);
        return $this->sendResponse($paymentIntent->fresh('accounts'), 'Payment collection settled manually.');
    }
    public function refund(Request $request, PaymentIntent $paymentIntent, PaystackPaymentCollectionGateway $gateway, PaymentGatewayConfigurationService $config) {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:' . $paymentIntent->amount_received], 'reason' => ['required', 'string', 'max:500']]);
        if ($paymentIntent->provider !== 'paystack' || $paymentIntent->status !== 'paid') return $this->sendError('Only settled Paystack collections can be refunded.', [], 422);
        $result = $gateway->refund($paymentIntent->reference, (float) $data['amount'], $data['reason'], $config->getConfig('paystack'));
        $paymentIntent->update([
            'metadata' => array_merge($paymentIntent->metadata ?? [], ['refund' => $result['data'] ?? $result, 'refund_reason' => $data['reason']]),
        ]);
        return $this->sendResponse($paymentIntent->fresh(), 'Refund queued with Paystack.');
    }
}
