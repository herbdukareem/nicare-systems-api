<?php

namespace App\Services;

use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Services\Billing\BillingCheckoutService;
use App\Services\Billing\BillingPaymentVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PublicPremiumPinPurchaseService
{
    public function __construct(
        private PremiumCoverageService $premiumCoverageService,
        private BillingCheckoutService $billingCheckoutService,
        private BillingPaymentVerificationService $verificationService
    ) {
    }

    public function create(array $data): array
    {
        $plan = PremiumPlan::findOrFail($data['premium_plan_id']);
        $paymentMethod = $data['payment_method'] ?? 'online_payment';

        if (!$plan->isSelfEnrollmentEnabled()) {
            throw new RuntimeException('The selected premium plan is not available for public PIN purchase.');
        }

        if (!$plan->requiresPayment()) {
            throw new RuntimeException('This premium plan does not require a paid Premium PIN.');
        }

        if ($paymentMethod === 'bank_transfer' && !$plan->supportsBankTransfer()) {
            throw new RuntimeException('This premium plan does not have a dedicated bank transfer account configured yet.');
        }

        $quantity = (int) $data['quantity'];
        $reference = $this->uniqueReference();
        $token = Str::random(64);
        $paymentCollection = $paymentMethod === 'bank_transfer'
            ? $plan->bankTransferDetails($reference)
            : null;

        $purchase = $this->premiumCoverageService->createPurchase([
            'premium_plan_id' => $plan->id,
            'payer_type' => $data['purchaser_type'] === 'agent' ? 'institution' : 'individual',
            'payer_name' => $data['payer_name'],
            'payer_phone' => $data['payer_phone'],
            'payer_email' => $data['payer_email'],
            'payer_details' => [
                'channel' => 'public_premium_pin_purchase',
                'purchaser_type' => $data['purchaser_type'],
                'docket_token' => $token,
                'bank_transfer_account' => $paymentCollection,
            ],
            'payment_method' => $paymentMethod,
            'payment_status' => 'pending',
            'payment_reference' => $reference,
            'quantity' => $quantity,
            'amount' => (float) $plan->amount * $quantity,
            'sold_by' => null,
        ]);

        $checkout = null;

        if ($paymentMethod === 'online_payment') {
            try {
                $checkout = $this->billingCheckoutService->initializePurchaseCheckout(
                    $purchase,
                    '/enroll/pins?checkout_return=1&docket_token=' . urlencode($token)
                );
            } catch (\Throwable $exception) {
                $purchase->delete();
                throw $exception;
            }

            $purchase->update([
                'gateway_code' => $checkout['provider'] ?? $purchase->gateway_code,
                'gateway_status' => $checkout['status'] ?? 'initialized',
                'authorization_url' => $checkout['authorization_url'] ?? null,
                'gateway_access_code' => $checkout['access_code'] ?? null,
                'gateway_response' => $checkout['raw_response'] ?? null,
            ]);
        }

        return [
            'purchase' => $purchase->fresh(['plan']),
            'checkout' => $checkout,
            'payment_collection' => $paymentCollection,
            'docket_token' => $token,
        ];
    }

    public function verify(string $reference, string $token): array
    {
        $purchase = $this->findAccessiblePurchase($reference, $token);
        $result = $this->verificationService->verifyPurchase($purchase);
        $purchase = $result['purchase'];

        if (($result['verification']['paid'] ?? false) || $purchase->payment_status === 'confirmed') {
            $this->ensurePinsGenerated($purchase);
        }

        return [
            ...$result,
            'pins' => $purchase->fresh()->pins()->with('plan')->get(),
            'docket_token' => $token,
        ];
    }

    public function findAccessiblePurchase(string $reference, string $token): PremiumPurchase
    {
        $purchase = PremiumPurchase::with(['plan', 'pins.plan'])
            ->where('payment_reference', $reference)
            ->firstOrFail();

        if (
            data_get($purchase->payer_details, 'channel') !== 'public_premium_pin_purchase'
            || !hash_equals((string) data_get($purchase->payer_details, 'docket_token'), $token)
        ) {
            throw new RuntimeException('The Premium PIN purchase link is invalid or incomplete.');
        }

        return $purchase;
    }

    public function ensurePinsGenerated(PremiumPurchase $purchase): PremiumPurchase
    {
        return DB::transaction(function () use ($purchase) {
            $lockedPurchase = PremiumPurchase::with('plan')->lockForUpdate()->findOrFail($purchase->id);

            if ($lockedPurchase->payment_status !== 'confirmed') {
                throw new RuntimeException('Premium PINs are available only after payment is confirmed.');
            }

            $remaining = max(0, (int) $lockedPurchase->quantity - $lockedPurchase->pins()->count());

            if ($remaining > 0) {
                $this->premiumCoverageService->generatePins($lockedPurchase->plan, $remaining, $lockedPurchase);
            }

            return $lockedPurchase->fresh(['plan', 'pins.plan']);
        });
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'PIN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
        } while (PremiumPurchase::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
