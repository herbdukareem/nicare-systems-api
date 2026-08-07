<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Services\Billing\BillingCheckoutService;
use App\Services\Billing\PaymentCollectionConfigurationService;
use App\Services\Billing\PaymentCollectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PublicEnrollmentService
{
    public function __construct(
        private PremiumCoverageService $premiumCoverageService,
        private BillingCheckoutService $billingCheckoutService,
        private PaymentCollectionConfigurationService $collectionSettings,
        private PaymentCollectionService $collectionService,
        private SystemAuditUserResolver $systemAuditUserResolver,
        private NinProviderConfigService $ninProviderConfigService,
        private NinVerificationService $ninVerificationService,
        private EnrollmentLocationResolver $locationResolver
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submitApplication(array $data): array
    {
        $plan = PremiumPlan::with(['programme', 'benefitPackage', 'fundingType'])->findOrFail($data['premium_plan_id']);
        $ninPolicy = $this->ninProviderConfigService->publicEnrollmentConfig();
        $enrollmentMethod = $data['enrollment_method'] ?? 'online_payment';
        $pin = null;

        if (!$plan->isSelfEnrollmentEnabled()) {
            throw new RuntimeException('The selected premium plan is not available for self-enrollment.');
        }

        if ($enrollmentMethod === 'premium_pin') {
            $pin = $this->premiumCoverageService->validatePin($data['premium_pin']);

            if ((int) $pin->premium_plan_id !== (int) $plan->id) {
                throw new RuntimeException('Premium PIN does not belong to the selected premium plan.');
            }
        }

        if ($enrollmentMethod === 'bank_transfer' && !(bool) ($this->collectionSettings->get()['enabled'] ?? false) && !$plan->supportsBankTransfer()) {
            throw new RuntimeException('The selected premium plan does not currently support direct bank transfer.');
        }

        $facility = Facility::findOrFail($data['facility_id']);
        $data = $this->locationResolver->resolve($data);

        if (empty($data['lga_id']) || empty($data['ward_id'])) {
            throw new RuntimeException('The selected facility is missing a complete ward/LGA assignment.');
        }

        $requiresPublicNinVerification = $ninPolicy['enabled'] && filled($data['nin'] ?? null);
        $planAmountDue = $enrollmentMethod === 'premium_pin'
            ? 0.0
            : ($plan->requiresPayment() ? (float) $plan->amount : 0.0);
        $ninVerificationFee = $requiresPublicNinVerification
            && ($enrollmentMethod !== 'premium_pin' || (bool) $ninPolicy['charge_for_premium_pin'])
            ? round((float) $ninPolicy['fee_amount'], 2)
            : 0.0;
        $totalDue = round($planAmountDue + $ninVerificationFee, 2);
        $requiresPayment = $totalDue > 0;
        $requiresHostedPayment = $requiresPayment
            && ($enrollmentMethod === 'online_payment' || ($enrollmentMethod === 'premium_pin' && $ninVerificationFee > 0));
        $usesBankTransfer = $requiresPayment && $enrollmentMethod === 'bank_transfer';

        if ($requiresHostedPayment && blank($data['email'] ?? null)) {
            throw new RuntimeException('Email address is required before checkout can continue for this enrollment.');
        }

        $paymentReference = $requiresPayment
            ? (($data['payment_reference'] ?? null) ?: $this->generatedPaymentReference())
            : null;
        $passportPath = $this->storePassport($data['passport'] ?? null);
        $paymentBreakdown = [
            'plan_amount' => round($planAmountDue, 2),
            'nin_verification_fee' => round($ninVerificationFee, 2),
            'total_amount' => round($totalDue, 2),
        ];

        $paymentCheckout = null;
        $paymentCollection = null;

        if ($requiresHostedPayment) {
            $paymentCheckout = $this->billingCheckoutService->initializePublicEnrollmentCheckout(
                $plan,
                [
                    'email' => $data['email'],
                    'amount' => $totalDue,
                    'metadata' => [
                        'channel' => 'self_service_enrollment',
                        'payment_breakdown' => $paymentBreakdown,
                        'enrollment_method' => $enrollmentMethod,
                        'lga_id' => $data['lga_id'],
                        'ward_id' => $data['ward_id'],
                        'facility_id' => $data['facility_id'],
                    ],
                ],
                $paymentReference
            );
        }

        $virtualCollectionsEnabled = (bool) ($this->collectionSettings->get()['enabled'] ?? false);
        if ($usesBankTransfer && !$virtualCollectionsEnabled) {
            $paymentCollection = $plan->bankTransferDetails($paymentReference);
        }

        return DB::transaction(function () use (
            $data,
            $plan,
            $pin,
            $ninPolicy,
            $enrollmentMethod,
            $requiresPayment,
            $requiresHostedPayment,
            $requiresPublicNinVerification,
            $usesBankTransfer,
            $paymentReference,
            $paymentCheckout,
            $paymentCollection,
            $passportPath,
            $paymentBreakdown
            , $virtualCollectionsEnabled
        ) {
            $purchase = null;
            $purchaseDetails = [
                'channel' => 'self_service_enrollment',
                'enrollment_method' => $enrollmentMethod,
                'payment_purpose' => $this->paymentPurpose($enrollmentMethod, $paymentBreakdown),
                'requires_public_nin_verification' => $requiresPublicNinVerification,
                'payment_breakdown' => $paymentBreakdown,
                'facility_id' => $data['facility_id'],
                'lga_id' => $data['lga_id'],
                'ward_id' => $data['ward_id'],
                'bank_transfer_account' => $paymentCollection,
            ];

            if ($requiresPayment) {
                $purchase = $this->premiumCoverageService->createPurchase([
                    'premium_plan_id' => $plan->id,
                    'payer_type' => 'individual',
                    'payer_name' => trim($data['first_name'] . ' ' . $data['last_name']),
                    'payer_phone' => $data['phone'] ?? null,
                    'payer_email' => $data['email'] ?? null,
                    'payer_details' => $purchaseDetails,
                    'payment_method' => $usesBankTransfer ? 'bank_transfer' : 'online_payment',
                    'payment_status' => 'pending',
                    'payment_reference' => $paymentReference,
                    'gateway_code' => $paymentCheckout['provider'] ?? $plan->payment_gateway,
                    'gateway_status' => $paymentCheckout['status'] ?? 'initialized',
                    'authorization_url' => $paymentCheckout['authorization_url'] ?? null,
                    'gateway_access_code' => $paymentCheckout['access_code'] ?? null,
                    'gateway_response' => $paymentCheckout['raw_response'] ?? null,
                    'quantity' => 1,
                    'amount' => $paymentBreakdown['total_amount'],
                    'sold_by' => null,
                ]);

                if ($usesBankTransfer && $virtualCollectionsEnabled) {
                    $paymentCollection = $this->collectionService->createForPurchase(
                        $purchase,
                        $this->collectionSettings->get()['default_mode'] ?? 'per_payment',
                        'premium_enrollment',
                        ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]
                    );
                }
            }

            $enrollee = Enrollee::create([
                'nin' => $data['nin'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'],
                'sex' => (int) $data['sex'],
                'marital_status' => $data['marital_status'] ?? null,
                'address' => $data['address'] ?? null,
                'image_url' => $passportPath,
                'facility_id' => $data['facility_id'],
                'lga_id' => $data['lga_id'],
                'ward_id' => $data['ward_id'],
                'insurance_programme_id' => $plan->insurance_programme_id,
                'premium_plan_id' => $plan->id,
                'premium_purchase_id' => $purchase?->id,
                'benefit_package_id' => $plan->benefit_package_id,
                'funding_type_id' => $plan->funding_type_id,
                'status' => Enrollee::STATUS_PENDING,
                'relationship_to_principal' => 1,
                'created_by' => $this->systemActorId(),
                'password' => Hash::make($data['password']),
                'enrollment_date' => now(),
                'enrollment_source' => 'self_service',
                'nin_verification_status' => blank($data['nin'] ?? null)
                    ? Enrollee::NIN_VERIFICATION_NOT_PROVIDED
                    : Enrollee::NIN_VERIFICATION_NOT_STARTED,
            ]);

            if ($purchase) {
                $purchaseDetails['enrollee_id'] = $enrollee->id;
                $purchaseDetails['enrollee_identifier'] = $enrollee->enrollee_id;
            }

            if ($pin && $requiresPayment && $paymentBreakdown['nin_verification_fee'] > 0) {
                $reservedPin = $this->premiumCoverageService->reservePinForPendingEnrollment(
                    $pin,
                    $enrollee,
                    $plan,
                    (int) $ninPolicy['pin_reservation_minutes']
                );

                $purchaseDetails['reserved_premium_pin_id'] = $reservedPin->id;
                $purchaseDetails['reserved_premium_pin_serial'] = $reservedPin->serial_number;
            } elseif ($pin) {
                $enrollee = $this->premiumCoverageService->usePinForPendingEnrollment($pin, $enrollee, $plan);
            }

            if ($purchase) {
                $purchase->update(['payer_details' => $purchaseDetails]);
                $purchase = $purchase->fresh(['plan']);
            }

            $ninVerification = null;

            if (!$requiresPayment && $requiresPublicNinVerification) {
                $ninVerification = $this->attemptPublicNinVerification($enrollee);
            }

            $enrollee = $enrollee->fresh([
                'premiumPlan',
                'premiumPin',
                'premiumPurchase',
                'benefitPackage',
                'fundingType',
                'facility',
                'lga',
                'ward',
                'insuranceProgramme',
            ]);

            return [
                'enrollee' => $enrollee,
                'purchase' => $purchase,
                'requires_payment' => $requiresPayment,
                'enrollment_method' => $enrollmentMethod,
                'payment_checkout' => $paymentCheckout,
                'payment_collection' => $paymentCollection,
                'payment_breakdown' => $paymentBreakdown,
                'nin_verification' => $ninVerification,
                'next_steps' => $this->buildNextSteps(
                    $requiresPayment,
                    $requiresHostedPayment,
                    $usesBankTransfer,
                    $enrollmentMethod,
                    $paymentReference,
                    $paymentCollection,
                    $paymentBreakdown,
                    $requiresPublicNinVerification,
                    $ninVerification
                ),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function finalizePaymentVerification(PremiumPurchase $purchase): array
    {
        if (data_get($purchase->payer_details, 'channel') !== 'self_service_enrollment') {
            return [];
        }

        $enrollee = Enrollee::with([
            'premiumPlan',
            'premiumPin',
            'premiumPurchase',
            'benefitPackage',
            'fundingType',
            'facility',
            'lga',
            'ward',
            'insuranceProgramme',
        ])->where('premium_purchase_id', $purchase->id)->first();

        if (!$enrollee && data_get($purchase->payer_details, 'enrollee_id')) {
            $enrollee = Enrollee::with([
                'premiumPlan',
                'premiumPin',
                'premiumPurchase',
                'benefitPackage',
                'fundingType',
                'facility',
                'lga',
                'ward',
                'insuranceProgramme',
            ])->find(data_get($purchase->payer_details, 'enrollee_id'));
        }

        if (!$enrollee) {
            return [];
        }

        $warnings = [];
        $ninVerification = null;
        $pinApplied = blank(data_get($purchase->payer_details, 'reserved_premium_pin_id'));

        if ($purchase->payment_status === 'confirmed') {
            $reservedPinId = data_get($purchase->payer_details, 'reserved_premium_pin_id');

            if ($reservedPinId && !$enrollee->premium_pin_id) {
                try {
                    $pin = PremiumPin::query()->findOrFail($reservedPinId);
                    $plan = $purchase->plan()->firstOrFail();
                    $enrollee = $this->premiumCoverageService->useReservedPinForPendingEnrollment($pin, $enrollee, $plan);
                    $pinApplied = true;
                } catch (\Throwable $exception) {
                    $warnings[] = $exception->getMessage();
                }
            }

            if ($this->ninProviderConfigService->publicEnrollmentConfig()['enabled'] && filled($enrollee->nin)) {
                $ninVerification = $this->attemptPublicNinVerification($enrollee);

                if (($ninVerification['message'] ?? null) && !($ninVerification['verified'] ?? false)) {
                    $warnings[] = (string) $ninVerification['message'];
                }
            }
        }

        $enrollee = $enrollee->fresh([
            'premiumPlan',
            'premiumPin',
            'premiumPurchase',
            'benefitPackage',
            'fundingType',
            'facility',
            'lga',
            'ward',
            'insuranceProgramme',
        ]);

        return [
            'enrollee' => $enrollee,
            'nin_verification' => $ninVerification,
            'pin_applied' => $pinApplied,
            'payment_breakdown' => data_get($purchase->payer_details, 'payment_breakdown', [
                'plan_amount' => round((float) $purchase->amount, 2),
                'nin_verification_fee' => 0.0,
                'total_amount' => round((float) $purchase->amount, 2),
            ]),
            'warnings' => $warnings,
            'next_steps' => $this->buildVerificationNextSteps($purchase, $enrollee, $ninVerification, $warnings),
        ];
    }

    private function storePassport(mixed $passport): ?string
    {
        if (!$passport instanceof UploadedFile) {
            return null;
        }

        $disk = (string) config('filesystems.enrollee_passport_disk', 'public');
        $path = Storage::disk($disk)->putFile('enrollees/passports', $passport, 'public');

        return Storage::disk($disk)->url($path);
    }

    private function generatedPaymentReference(): string
    {
        do {
            $reference = 'SELF-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        } while (PremiumPurchase::where('payment_reference', $reference)->exists());

        return $reference;
    }

    private function systemActorId(): int
    {
        if (auth()->id()) {
            return (int) auth()->id();
        }

        return $this->systemAuditUserResolver->resolveId();
    }

    private function systemAuditUser(): User
    {
        return User::query()->findOrFail($this->systemAuditUserResolver->resolveId());
    }

    /**
     * @param  array<string, mixed>  $paymentBreakdown
     */
    private function paymentPurpose(string $enrollmentMethod, array $paymentBreakdown): string
    {
        $planAmount = (float) ($paymentBreakdown['plan_amount'] ?? 0);
        $ninFee = (float) ($paymentBreakdown['nin_verification_fee'] ?? 0);

        if ($enrollmentMethod === 'premium_pin') {
            return 'nin_verification_fee';
        }

        if ($planAmount > 0 && $ninFee > 0) {
            return 'plan_and_nin_verification';
        }

        if ($ninFee > 0) {
            return 'nin_verification_fee';
        }

        return $enrollmentMethod === 'bank_transfer' ? 'bank_transfer_plan_only' : 'plan_only';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attemptPublicNinVerification(Enrollee $enrollee): ?array
    {
        if (blank($enrollee->nin)) {
            return null;
        }

        try {
            $result = $this->ninVerificationService->verify($enrollee->fresh(), $this->systemAuditUser());

            return [
                'status' => $result['status'] ?? Enrollee::NIN_VERIFICATION_VERIFIED,
                'verified' => ($result['status'] ?? null) === Enrollee::NIN_VERIFICATION_VERIFIED,
                'provider_name' => $result['provider_name'] ?? null,
                'verified_at' => $result['verified_at'] ?? null,
                'cached' => (bool) ($result['cached'] ?? false),
                'comparison' => $result['comparison'] ?? null,
            ];
        } catch (RuntimeException $exception) {
            $fresh = $enrollee->fresh();

            return [
                'status' => $fresh->nin_verification_status ?: Enrollee::NIN_VERIFICATION_FAILED,
                'verified' => false,
                'provider_name' => $fresh->nin_verification_provider,
                'verified_at' => $fresh->nin_verified_at,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $paymentCollection
     * @param  array<string, mixed>  $paymentBreakdown
     * @param  array<string, mixed>|null  $ninVerification
     * @return array<int, string>
     */
    private function buildNextSteps(
        bool $requiresPayment,
        bool $requiresHostedPayment,
        bool $usesBankTransfer,
        string $enrollmentMethod,
        ?string $paymentReference,
        ?array $paymentCollection,
        array $paymentBreakdown,
        bool $requiresPublicNinVerification,
        ?array $ninVerification
    ): array {
        if ($requiresHostedPayment) {
            $steps = [
                'Complete the secure online payment using the launched checkout page.',
            ];

            if (($paymentBreakdown['nin_verification_fee'] ?? 0) > 0) {
                $steps[] = 'Once payment is confirmed, the system will run your live NIN verification automatically and attach the result to this enrollment.';
            }

            $steps[] = 'Your application will remain pending until payment is confirmed and enrollment review is completed.';
            $steps[] = 'Use your enrollee ID after approval to access the enrollee portal with the password you created.';

            return $steps;
        }

        if ($requiresPayment && $usesBankTransfer) {
            $bankName = data_get($paymentCollection, 'bank_name', 'the dedicated account');

            return [
                "Transfer the exact total amount into the dedicated {$bankName} account shown for this plan.",
                $paymentReference
                    ? "Use {$paymentReference} as your transfer narration or depositor reference so reconciliation can match your payment quickly."
                    : 'Keep your transfer narration and receipt available for reconciliation.',
                ($paymentBreakdown['nin_verification_fee'] ?? 0) > 0
                    ? 'Your application will remain pending until the transfer is confirmed and live NIN verification is completed.'
                    : 'Your application will remain pending until the transfer is confirmed and your enrollment review is completed.',
                'Use your enrollee ID after approval to access the enrollee portal with the password you created.',
            ];
        }

        $steps = [];

        if ($enrollmentMethod === 'premium_pin') {
            $steps[] = 'Your Premium PIN has been accepted and cannot be used again.';
        }

        if ($requiresPublicNinVerification && ($ninVerification['verified'] ?? false)) {
            $steps[] = 'Your NIN was verified successfully during submission.';
        } elseif ($requiresPublicNinVerification) {
            $steps[] = $ninVerification['message'] ?? 'Your NIN verification could not be completed automatically. An enrollment officer can review and retry it.';
        } else {
            $steps[] = 'Your application has been submitted for approval.';
        }

        $steps[] = 'Use your enrollee ID after approval to access the enrollee portal with the password you created.';

        return $steps;
    }

    /**
     * @param  array<string, mixed>|null  $ninVerification
     * @param  array<int, string>  $warnings
     * @return array<int, string>
     */
    private function buildVerificationNextSteps(
        PremiumPurchase $purchase,
        Enrollee $enrollee,
        ?array $ninVerification,
        array $warnings
    ): array {
        if ($purchase->payment_status !== 'confirmed') {
            return [
                $purchase->payment_method === 'bank_transfer'
                    ? 'This transfer is still awaiting manual confirmation.'
                    : 'Complete the pending payment, then return here to verify the transaction.',
                'Your enrollment application will remain pending until payment is confirmed.',
            ];
        }

        $steps = [
            'Payment has been confirmed for this enrollment.',
        ];

        if (($ninVerification['verified'] ?? false) === true) {
            $steps[] = 'Live NIN verification has been completed and attached to the enrollment review record.';
        } elseif (filled($enrollee->nin)) {
            $steps[] = $ninVerification['message'] ?? 'Live NIN verification still needs review or retry.';
        }

        if ($warnings !== []) {
            $steps[] = 'One or more follow-up checks still need staff attention before approval.';
        }

        $steps[] = 'An enrollment officer will complete approval before coverage becomes active.';

        return $steps;
    }
}
