<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Services\Billing\BillingCheckoutService;
use App\Services\Billing\BillingPaymentVerificationService;
use App\Services\Billing\PaymentCollectionConfigurationService;
use App\Services\Billing\PaymentCollectionService;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EnrolleePortalRenewalService
{
    public function __construct(
        private PremiumCoverageService $premiumCoverageService,
        private BillingCheckoutService $billingCheckoutService,
        private BillingPaymentVerificationService $verificationService,
        private PaymentCollectionConfigurationService $collectionSettings,
        private PaymentCollectionService $collectionService
    ) {
    }

    public function create(Enrollee $enrollee, int $planId, ?string $collectionMode = null, ?string $callbackPath = null, ?string $payerEmail = null, ?string $paymentMethod = null): array
    {
        $plan = PremiumPlan::with(['programme', 'benefitPackage', 'fundingType'])->findOrFail($planId);

        if ($plan->status !== 'active') {
            throw new RuntimeException('The selected premium plan is not active.');
        }

        $quote = $plan->requiresPayment()
            ? $this->billingCheckoutService->quotePremiumPlanCheckout($plan)
            : ['base_amount' => (float) $plan->amount, 'processing_fee' => 0, 'customer_total' => (float) $plan->amount];

        $purchase = $this->premiumCoverageService->createPurchase([
            'premium_plan_id' => $plan->id,
            'funding_type_id' => $plan->funding_type_id,
            'payer_type' => 'individual',
            'payer_name' => $enrollee->full_name ?: trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->last_name ?? '')),
            'payer_phone' => $enrollee->phone,
            'payer_email' => $payerEmail ?: $enrollee->email,
            'payer_details' => [
                'channel' => 'enrollee_portal_renewal',
                'enrollee_id' => $enrollee->id,
                'enrollee_identifier' => $enrollee->enrollee_id,
            ],
            'payment_method' => $plan->requiresPayment() ? 'online_payment' : 'manual_confirmation',
            'payment_status' => $plan->requiresPayment() ? 'pending' : 'confirmed',
            'payment_reference' => $this->uniqueReference(),
            'quantity' => 1,
            // amount remains the amount sent to the payment provider for
            // backwards compatibility. The individual quote fields preserve
            // the plan price and the transparent customer surcharge.
            'amount' => $quote['customer_total'],
            'base_amount' => $quote['base_amount'],
            'processing_fee' => $quote['processing_fee'],
            'customer_total' => $quote['customer_total'],
            'sold_by' => null,
        ]);

        if (!$plan->requiresPayment()) {
            $purchase->update([
                'confirmed_at' => now(),
                'verified_at' => now(),
                'paid_at' => now(),
            ]);

            $enrollee = $this->premiumCoverageService->renewEnrolleeCoverage($enrollee, $plan, $purchase, $enrollee->facility_id);

            return [
                'purchase' => $purchase->fresh(['plan', 'fundingType']),
                'checkout' => null,
                'requires_payment' => false,
                'enrollee' => $enrollee,
                'renewed' => true,
            ];
        }

        if (blank($purchase->payer_email)) {
            throw new RuntimeException('The enrollee account must have an email address before online renewal can continue.');
        }

        $useVirtualAccount = $paymentMethod === 'bank_transfer'
            || ($paymentMethod === null && (bool) ($this->collectionSettings->get()['enabled'] ?? false));

        if ($useVirtualAccount) {
            $collection = $this->collectionService->createForPurchase($purchase, $collectionMode ?: ($this->collectionSettings->get()['default_mode'] ?? 'per_payment'), 'coverage_renewal', ['name' => $purchase->payer_name, 'email' => $purchase->payer_email, 'phone' => $purchase->payer_phone]);
            return ['purchase' => $purchase->fresh(['plan', 'fundingType']), 'checkout' => null, 'payment_collection' => $collection, 'requires_payment' => true, 'renewed' => false];
        }

        $checkout = $this->billingCheckoutService->initializePurchaseCheckout(
            $purchase,
            $callbackPath ?? '/enroll/plans?checkout_return=1'
        );

        $purchase->update([
            'gateway_code' => $checkout['provider'] ?? $purchase->gateway_code,
            'gateway_status' => $checkout['status'] ?? 'initialized',
            'authorization_url' => $checkout['authorization_url'] ?? null,
            'gateway_access_code' => $checkout['access_code'] ?? null,
            'gateway_response' => $checkout['raw_response'] ?? null,
        ]);

        return [
            'purchase' => $purchase->fresh(['plan', 'fundingType']),
            'checkout' => $checkout,
            'payment_collection' => null,
            'requires_payment' => true,
            'renewed' => false,
        ];
    }

    public function quote(Enrollee $enrollee): array
    {
        $plan = $enrollee->premiumPlan;
        if (!$plan || $plan->status !== 'active') {
            throw new RuntimeException('This enrollee does not have an active premium plan available for renewal.');
        }

        $quote = $plan->requiresPayment()
            ? $this->billingCheckoutService->quotePremiumPlanCheckout($plan)
            : ['base_amount' => (float) $plan->amount, 'processing_fee' => 0, 'customer_total' => (float) $plan->amount, 'currency' => 'NGN'];

        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'requires_payment' => $plan->requiresPayment(),
            ...$quote,
        ];
    }

    public function pendingCollection(Enrollee $enrollee): ?array
    {
        $purchaseIds = PremiumPurchase::query()
            ->where('payment_status', 'pending')
            ->where('payer_details->channel', 'enrollee_portal_renewal')
            ->where('payer_details->enrollee_id', $enrollee->id)
            ->pluck('id');

        if ($purchaseIds->isEmpty()) {
            return null;
        }

        $intent = PaymentIntent::with('accounts')
            ->where('payable_type', PremiumPurchase::class)
            ->whereIn('payable_id', $purchaseIds)
            ->whereIn('status', ['pending', 'awaiting_payment', 'review_required'])
            ->latest()
            ->first();

        return $intent ? $this->collectionService->present($intent) : null;
    }

    public function verify(Enrollee $enrollee, string $reference): array
    {
        return $this->verifyForEnrollee($enrollee, $reference);
    }

    public function verifyForEnrollee(Enrollee $enrollee, string $reference): array
    {
        $purchase = $this->findAccessiblePurchase($enrollee, $reference);

        $result = $purchase->payment_method === 'manual_confirmation'
            ? [
                'purchase' => $purchase->fresh(['plan', 'fundingType']),
                'verification' => [
                    'provider' => 'manual_confirmation',
                    'status' => $purchase->payment_status === 'confirmed' ? 'confirmed' : 'pending_manual_confirmation',
                    'paid' => $purchase->payment_status === 'confirmed',
                ],
            ]
            : $this->verificationService->verifyPurchase($purchase);

        $purchase = $result['purchase'];
        $renewedEnrollee = null;

        if ($purchase->payment_status === 'confirmed') {
            $renewedEnrollee = $this->applyConfirmedRenewal($enrollee, $purchase);
        }

        return [
            ...$result,
            'enrollee' => $renewedEnrollee,
            'renewed' => $renewedEnrollee !== null,
        ];
    }

    private function findAccessiblePurchase(Enrollee $enrollee, string $reference): PremiumPurchase
    {
        $purchase = PremiumPurchase::with(['plan', 'fundingType', 'pins.plan'])
            ->where('payment_reference', $reference)
            ->firstOrFail();

        if (
            data_get($purchase->payer_details, 'channel') !== 'enrollee_portal_renewal'
            || (int) data_get($purchase->payer_details, 'enrollee_id') !== (int) $enrollee->id
        ) {
            throw new RuntimeException('This renewal transaction does not belong to the signed-in enrollee.');
        }

        return $purchase;
    }

    public function applyConfirmedRenewal(Enrollee $enrollee, PremiumPurchase $purchase): Enrollee
    {
        return DB::transaction(function () use ($enrollee, $purchase) {
            $lockedEnrollee = Enrollee::lockForUpdate()->findOrFail($enrollee->id);
            $lockedPurchase = PremiumPurchase::with(['plan', 'pins.plan'])->lockForUpdate()->findOrFail($purchase->id);

            $existingPin = $lockedPurchase->pins()
                ->where('used_by_enrollee_id', $lockedEnrollee->id)
                ->first();

            if ($existingPin) {
                return $lockedEnrollee->fresh([
                    'facility', 'premiumPlan', 'benefitPackage', 'lga', 'ward',
                    'insuranceProgramme', 'fundingType', 'benefactor',
                ]);
            }

            if ($lockedPurchase->pins()->count() < 1) {
                $this->premiumCoverageService->generatePins($lockedPurchase->plan, 1, $lockedPurchase);
                $lockedPurchase->load(['plan', 'pins.plan']);
            }

            $pin = $lockedPurchase->pins
                ->first(fn ($item) => !$item->used_at && !$item->used_by_enrollee_id);

            if (!$pin) {
                throw new RuntimeException('No usable premium PIN is available for this renewal.');
            }

            return $this->premiumCoverageService->usePinForRenewal($pin, $lockedEnrollee, $lockedEnrollee->facility_id, $lockedPurchase);
        });
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'REN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8));
        } while (PremiumPurchase::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
