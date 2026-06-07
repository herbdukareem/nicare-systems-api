<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Services\Billing\BillingCheckoutService;
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
        private BillingCheckoutService $billingCheckoutService
    ) {
    }

    public function submitApplication(array $data): array
    {
        $plan = PremiumPlan::with(['programme', 'benefitPackage'])->findOrFail($data['premium_plan_id']);

        if (!$plan->isSelfEnrollmentEnabled()) {
            throw new RuntimeException('The selected premium plan is not available for self-enrollment.');
        }

        $facility = Facility::findOrFail($data['facility_id']);

        if ((int) $facility->lga_id !== (int) $data['lga_id']) {
            throw new RuntimeException('The selected facility does not belong to the selected LGA.');
        }

        if ((int) $facility->ward_id !== (int) $data['ward_id']) {
            throw new RuntimeException('The selected facility does not belong to the selected ward.');
        }

        if ($plan->requiresPayment() && blank($data['email'] ?? null)) {
            throw new RuntimeException('Email address is required for plans that use secure online payment.');
        }

        $paymentReference = $plan->requiresPayment()
            ? (($data['payment_reference'] ?? null) ?: $this->generatedPaymentReference())
            : null;
        $passportPath = $this->storePassport($data['passport'] ?? null);

        $paymentCheckout = null;

        if ($plan->requiresPayment()) {
            $paymentCheckout = $this->billingCheckoutService->initializePremiumEnrollmentPlanCheckout(
                $plan,
                [
                    'email' => $data['email'],
                    'metadata' => [
                        'channel' => 'self_service_enrollment',
                        'lga_id' => $data['lga_id'],
                        'ward_id' => $data['ward_id'],
                        'facility_id' => $data['facility_id'],
                    ],
                ],
                $paymentReference
            );
        }

        $result = DB::transaction(function () use ($data, $plan, $paymentReference, $paymentCheckout, $passportPath) {
            $purchase = null;

            if ($plan->requiresPayment()) {
                $purchase = $this->premiumCoverageService->createPurchase([
                    'premium_plan_id' => $plan->id,
                    'payer_type' => 'individual',
                    'payer_name' => trim($data['first_name'] . ' ' . $data['last_name']),
                    'payer_phone' => $data['phone'] ?? null,
                    'payer_email' => $data['email'] ?? null,
                    'payer_details' => [
                        'channel' => 'self_service_enrollment',
                        'facility_id' => $data['facility_id'],
                        'lga_id' => $data['lga_id'],
                        'ward_id' => $data['ward_id'],
                    ],
                    'payment_method' => 'online_payment',
                    'payment_status' => 'pending',
                    'payment_reference' => $paymentReference,
                    'gateway_code' => $paymentCheckout['provider'] ?? $plan->payment_gateway,
                    'gateway_status' => $paymentCheckout['status'] ?? 'initialized',
                    'authorization_url' => $paymentCheckout['authorization_url'] ?? null,
                    'gateway_access_code' => $paymentCheckout['access_code'] ?? null,
                    'gateway_response' => $paymentCheckout['raw_response'] ?? null,
                    'quantity' => 1,
                    'amount' => $plan->amount,
                    'sold_by' => null,
                ]);
            }

            $enrollee = Enrollee::create([
                'enrollee_id' => $this->generatedEnrolleeId(),
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

            return [
                'enrollee' => $enrollee->load(['premiumPlan', 'premiumPurchase', 'benefitPackage', 'facility', 'lga', 'ward', 'insuranceProgramme']),
                'purchase' => $purchase?->load(['plan']),
                'requires_payment' => $plan->requiresPayment(),
                'payment_checkout' => $paymentCheckout,
                'next_steps' => $plan->requiresPayment()
                    ? [
                        'Complete the secure online payment using the launched checkout page.',
                        'Your application will remain pending until payment is confirmed and an approval officer verifies your NIN.',
                        'Use your enrollee ID after approval to access the enrollee portal with the password you created.',
                    ]
                    : [
                        'Your application has been submitted for approval.',
                        'An approval officer will verify your NIN before activating your coverage.',
                        'Use your enrollee ID after approval to access the enrollee portal with the password you created.',
                    ],
            ];
        });

        return $result;
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

    private function generatedEnrolleeId(): string
    {
        do {
            $value = 'NG' . now()->format('ymdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Enrollee::where('enrollee_id', $value)->exists());

        return $value;
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

        $systemUser = User::where('username', 'system.audit')->first();

        if (!$systemUser) {
            $systemUser = User::factory()->create([
                'name' => 'System Audit',
                'username' => 'system.audit',
                'email' => 'system.audit@local.test',
            ]);
        }

        return (int) $systemUser->id;
    }
}
