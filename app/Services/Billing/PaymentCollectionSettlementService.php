<?php

namespace App\Services\Billing;

use App\Models\PaymentCollectionEvent;
use App\Models\PaymentIntent;
use App\Models\PremiumPurchase;
use App\Models\Enrollee;
use App\Services\EnrolleePortalRenewalService;
use App\Services\PublicEnrollmentService;
use App\Services\PremiumCoverageService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentCollectionSettlementService
{
    public function __construct(private PremiumCoverageService $coverage, private PublicEnrollmentService $publicEnrollment, private EnrolleePortalRenewalService $renewals) {}

    public function settlePaystack(array $payload): void
    {
        $eventId = (string) data_get($payload, 'data.id', data_get($payload, 'data.reference', ''));
        if ($eventId === '') throw new RuntimeException('Paystack event is missing an identifier.');
        DB::transaction(function () use ($payload, $eventId) {
            $event = PaymentCollectionEvent::firstOrCreate(['provider' => 'paystack', 'provider_event_id' => $eventId], ['event_type' => (string) data_get($payload, 'event', 'unknown'), 'payload' => $payload]);
            if ($event->processed_at) return;
            $reference = (string) data_get($payload, 'data.reference');
            $intent = PaymentIntent::where('provider', 'paystack')->where('reference', $reference)->lockForUpdate()->first();
            if (!$intent && data_get($payload, 'data.authorization.channel') === 'dedicated_nuban') {
                $accountNumber = (string) data_get($payload, 'data.authorization.receiver_bank_account_number');
                $amount = round(((int) data_get($payload, 'data.amount', 0)) / 100, 2);
                $matches = PaymentIntent::query()->where('provider', 'paystack')->where('collection_mode', 'per_payer')->whereIn('status', ['pending', 'awaiting_payment'])->where('amount_due', $amount)->where('expires_at', '>', now())->whereHas('accounts', fn ($query) => $query->where('account_number', $accountNumber))->lockForUpdate()->get();
                if ($matches->count() === 1) $intent = $matches->first();
            }
            if (!$intent) { $event->update(['status' => 'unmatched', 'processing_error' => 'No payment intent matches the provider reference.', 'processed_at' => now()]); return; }
            $event->update(['payment_intent_id' => $intent->id]);
            if (data_get($payload, 'event') !== 'charge.success') { $event->update(['status' => 'ignored', 'processed_at' => now()]); return; }
            $received = round(((int) data_get($payload, 'data.amount', 0)) / 100, 2);
            if ($received !== round((float) $intent->amount_due, 2)) { $intent->update(['status' => 'review_required', 'amount_received' => $received]); $event->update(['status' => 'review_required', 'processing_error' => 'The received amount does not equal the intent amount.', 'processed_at' => now()]); return; }
            if ($intent->expires_at?->isPast() && $intent->collection_mode === 'per_payment') { $intent->update(['status' => 'review_required', 'amount_received' => $received]); $event->update(['status' => 'review_required', 'processing_error' => 'The temporary account had expired.', 'processed_at' => now()]); return; }
            $intent->update(['status' => 'paid', 'amount_received' => $received, 'paid_at' => now()]);
            $purchase = $intent->payable;
            if ($purchase instanceof PremiumPurchase) {
                $purchase = $this->coverage->markPurchasePaidFromGateway($purchase, ['provider' => 'paystack', 'reference' => $reference, 'status' => 'success', 'paid' => true, 'raw_response' => $payload]);
                $channel = data_get($purchase->payer_details, 'channel');
                if ($channel === 'self_service_enrollment') $this->publicEnrollment->finalizePaymentVerification($purchase);
                if (in_array($channel, ['public_premium_pin_purchase', 'mobile_officer_pin_purchase'], true)) {
                    $remaining = max(0, (int) $purchase->quantity - $purchase->pins()->count());
                    if ($remaining > 0) $this->coverage->generatePins($purchase->plan()->firstOrFail(), $remaining, $purchase);
                }
                if ($channel === 'enrollee_portal_renewal' && ($enrolleeId = data_get($purchase->payer_details, 'enrollee_id'))) {
                    $enrollee = Enrollee::find($enrolleeId);
                    if ($enrollee) $this->renewals->applyConfirmedRenewal($enrollee, $purchase);
                }
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        });
    }
}
