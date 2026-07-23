<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\PayrollBatch;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PremiumCoverageService
{
    public function __construct(private PremiumAuditService $audit)
    {
    }

    public function generatePins(PremiumPlan $plan, int $quantity, ?PremiumPurchase $purchase = null): array
    {
        $batchCode = 'PIN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
        $pins = [];

        DB::transaction(function () use ($plan, $quantity, $purchase, $batchCode, &$pins) {
            for ($i = 0; $i < $quantity; $i++) {
                $pin = PremiumPin::create([
                    'premium_plan_id' => $plan->id,
                    'insurance_programme_id' => $plan->insurance_programme_id,
                    'benefit_package_id' => $plan->benefit_package_id,
                    'premium_purchase_id' => $purchase?->id,
                    'batch_code' => $batchCode,
                    'pin' => $this->uniquePin(),
                    'serial_number' => 'SN-' . Str::upper(Str::random(12)),
                    'amount' => $plan->amount,
                    'status' => $purchase ? PremiumPin::STATUS_SOLD : PremiumPin::STATUS_GENERATED,
                    'sold_at' => $purchase ? now() : null,
                    'sold_by' => $purchase ? auth()->id() : null,
                ]);
                $this->audit->record($pin, 'premium_pin_generated', "Premium PIN {$pin->serial_number} generated.", [], $pin->toArray());
                $pins[] = $pin;
            }
        });

        return $pins;
    }

    public function createPurchase(array $data): PremiumPurchase
    {
        $plan = PremiumPlan::findOrFail($data['premium_plan_id']);
        $quantity = (int) ($data['quantity'] ?? 1);

        $purchase = PremiumPurchase::create(array_merge($data, [
            'quantity' => $quantity,
            'amount' => $data['amount'] ?? ($plan->amount * $quantity),
            'payment_status' => $data['payment_status'] ?? 'pending',
            'gateway_code' => $data['gateway_code'] ?? $plan->payment_gateway,
            'gateway_status' => $data['gateway_status'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'gateway_access_code' => $data['gateway_access_code'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null,
            'sold_by' => $data['sold_by'] ?? auth()->id(),
        ]));

        $this->audit->record($purchase, 'premium_purchase_created', "Premium purchase {$purchase->id} created.", [], $purchase->toArray());

        return $purchase;
    }

    public function sellPin(PremiumPin $pin, PremiumPurchase $purchase): PremiumPin
    {
        $this->assertPinUsableForSale($pin);

        $old = $pin->toArray();
        $pin->update([
            'premium_purchase_id' => $purchase->id,
            'status' => PremiumPin::STATUS_SOLD,
            'sold_at' => now(),
            'sold_by' => auth()->id(),
        ]);

        $this->audit->record($pin, 'premium_pin_sold', "Premium PIN {$pin->serial_number} sold.", $old, $pin->fresh()->toArray());

        return $pin->fresh();
    }

    public function validatePin(string $pinValue): PremiumPin
    {
        $pin = PremiumPin::with('purchase', 'plan')->where('pin', $pinValue)->first();

        if (!$pin) {
            throw new InvalidArgumentException('Premium PIN not found.');
        }

        if ($pin->isExpired()) {
            $pin->update(['status' => PremiumPin::STATUS_EXPIRED]);
            throw new InvalidArgumentException('BR-14 violation: Premium PIN has expired.');
        }

        if ($pin->status === PremiumPin::STATUS_USED || $pin->used_at || $pin->used_by_enrollee_id) {
            throw new InvalidArgumentException('BR-13 violation: Premium PIN can only be used once.');
        }

        if ($pin->status !== PremiumPin::STATUS_SOLD) {
            throw new InvalidArgumentException('BR-14 violation: Premium PIN is not paid/sold or is already unavailable.');
        }

        if (!$pin->purchase || $pin->purchase->payment_status !== 'confirmed') {
            throw new InvalidArgumentException('BR-14 violation: Premium PIN cannot be used before payment confirmation.');
        }

        return $pin;
    }

    public function usePinForCoverage(PremiumPin $pin, Enrollee $enrollee, ?int $facilityId = null): Enrollee
    {
        $pin = $this->validatePin($pin->pin);

        if ($pin->used_at || $pin->used_by_enrollee_id) {
            throw new InvalidArgumentException('BR-13 violation: Premium PIN can only be used once.');
        }

        return DB::transaction(function () use ($pin, $enrollee, $facilityId) {
            $plan = $pin->plan;
            $approvalDate = now();
            $start = $plan->calculateCoverageStartDate($approvalDate);
            $end = $plan->calculateCoverageEndDate($start);

            $enrollee->update([
                'insurance_programme_id' => $plan->insurance_programme_id,
                'premium_plan_id' => $plan->id,
                'premium_pin_id' => $pin->id,
                'benefit_package_id' => $plan->benefit_package_id,
                'funding_type_id' => $plan->funding_type_id,
                'facility_id' => $facilityId ?? $enrollee->facility_id,
                'coverage_start_date' => $start->toDateString(),
                'coverage_end_date' => $end?->toDateString(),
                'status' => 1,
                'approval_date' => $enrollee->approval_date ?? $approvalDate,
                'approved_by' => $enrollee->approved_by ?? auth()->id(),
            ]);

            $old = $pin->toArray();
            $pin->update([
                'status' => PremiumPin::STATUS_USED,
                'used_at' => now(),
                'expires_at' => $end,
                'used_by_enrollee_id' => $enrollee->id,
            ]);
            $this->audit->record($pin, 'premium_pin_used', "Premium PIN {$pin->serial_number} used by enrollee {$enrollee->enrollee_id}.", $old, $pin->fresh()->toArray());

            return $enrollee->fresh(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility', 'fundingType', 'benefactor']);
        });
    }

    public function usePinForPendingEnrollment(PremiumPin $pin, Enrollee $enrollee, PremiumPlan $plan): Enrollee
    {
        return DB::transaction(function () use ($pin, $enrollee, $plan) {
            $lockedPin = PremiumPin::with(['purchase', 'plan'])->lockForUpdate()->findOrFail($pin->id);

            if ($lockedPin->isExpired()) {
                $lockedPin->update(['status' => PremiumPin::STATUS_EXPIRED]);
                throw new InvalidArgumentException('Premium PIN has expired.');
            }

            if (
                $lockedPin->status !== PremiumPin::STATUS_SOLD
                || $lockedPin->used_at
                || $lockedPin->used_by_enrollee_id
                || !$lockedPin->purchase
                || $lockedPin->purchase->payment_status !== 'confirmed'
            ) {
                throw new InvalidArgumentException('Premium PIN is invalid, unpaid, or has already been used.');
            }

            if ((int) $lockedPin->premium_plan_id !== (int) $plan->id) {
                throw new InvalidArgumentException('Premium PIN does not belong to the selected premium plan.');
            }

            $enrollee->update([
                'insurance_programme_id' => $plan->insurance_programme_id,
                'premium_plan_id' => $plan->id,
                'premium_pin_id' => $lockedPin->id,
                'premium_purchase_id' => $lockedPin->premium_purchase_id,
                'benefit_package_id' => $plan->benefit_package_id,
                'funding_type_id' => $plan->funding_type_id,
            ]);

            $old = $lockedPin->toArray();
            $lockedPin->update([
                'status' => PremiumPin::STATUS_USED,
                'used_at' => now(),
                'used_by_enrollee_id' => $enrollee->id,
            ]);

            $this->audit->record(
                $lockedPin,
                'premium_pin_used_for_pending_enrollment',
                "Premium PIN {$lockedPin->serial_number} used for pending enrollee {$enrollee->enrollee_id}.",
                $old,
                $lockedPin->fresh()->toArray()
            );

            return $enrollee->fresh();
        });
    }

    public function confirmPurchase(PremiumPurchase $purchase, ?int $confirmedBy = null): PremiumPurchase
    {
        if ($purchase->sold_by && (int) $purchase->sold_by === (int) ($confirmedBy ?? auth()->id())) {
            throw new InvalidArgumentException('Creator cannot approve own premium purchase where approval is required.');
        }

        $old = $purchase->toArray();
        $purchase->update([
            'payment_status' => 'confirmed',
            'confirmed_by' => $confirmedBy ?? auth()->id(),
            'confirmed_at' => now(),
            'paid_at' => $purchase->paid_at ?? now(),
        ]);

        $this->audit->record($purchase, 'premium_purchase_confirmed', "Premium purchase {$purchase->id} confirmed.", $old, $purchase->fresh()->toArray());

        return $purchase->fresh();
    }

    public function markPurchasePaidFromGateway(PremiumPurchase $purchase, array $verification): PremiumPurchase
    {
        if ($purchase->payment_status === 'confirmed') {
            return $purchase->fresh();
        }

        $old = $purchase->toArray();
        $purchase->update([
            'payment_status' => 'confirmed',
            'gateway_status' => $verification['status'] ?? 'success',
            'gateway_response' => $verification['raw_response'] ?? null,
            'paid_at' => $purchase->paid_at ?? now(),
            'confirmed_at' => $purchase->confirmed_at ?? now(),
            'verified_at' => now(),
        ]);

        $this->audit->record(
            $purchase,
            'premium_purchase_gateway_confirmed',
            "Premium purchase {$purchase->id} confirmed by online gateway verification.",
            $old,
            $purchase->fresh()->toArray()
        );

        return $purchase->fresh();
    }

    public function cancelPurchase(PremiumPurchase $purchase): PremiumPurchase
    {
        $old = $purchase->toArray();
        $purchase->update([
            'payment_status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => auth()->id(),
        ]);

        $this->audit->record($purchase, 'premium_purchase_cancelled', "Premium purchase {$purchase->id} cancelled.", $old, $purchase->fresh()->toArray());

        return $purchase->fresh();
    }

    public function activatePayrollBatch(PayrollBatch $batch): int
    {
        if ($batch->uploaded_by && (int) $batch->uploaded_by === (int) auth()->id()) {
            throw new InvalidArgumentException('Uploader cannot approve own payroll batch.');
        }

        $plan = PremiumPlan::findOrFail($batch->premium_plan_id);
        $count = 0;

        DB::transaction(function () use ($batch, $plan, &$count) {
            foreach ($batch->enrollees as $row) {
                $facility = $row->facility_id ? \App\Models\Facility::find($row->facility_id) : null;
                $lgaId = $row->lga_id ?? $facility?->lga_id;
                $wardId = $row->ward_id ?? $facility?->ward_id;

                if (!$lgaId || !$wardId) {
                    throw new InvalidArgumentException(
                        "Payroll batch row for {$row->first_name} {$row->last_name} is missing LGA/ward information and could not inherit it from the selected facility."
                    );
                }

                $enrollee = $row->enrollee_id ? Enrollee::find($row->enrollee_id) : null;
                if (!$enrollee) {
                    $enrollee = Enrollee::create([
                        'nin' => $row->nin,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'phone' => $row->phone,
                        'facility_id' => $row->facility_id,
                        'lga_id' => $lgaId,
                        'ward_id' => $wardId,
                        'created_by' => auth()->id(),
                        'status' => 1,
                        'enrollment_date' => now(),
                    ]);
                }

                $coverageStart = $plan->calculateCoverageStartDate(now());
                $coverageEnd = $plan->calculateCoverageEndDate($coverageStart);

                $enrollee->update([
                    'insurance_programme_id' => $plan->insurance_programme_id,
                    'enrollee_category_id' => $batch->enrollee_category_id,
                    'premium_plan_id' => $plan->id,
                    'benefit_package_id' => $plan->benefit_package_id,
                    'facility_id' => $row->facility_id ?? $enrollee->facility_id,
                    'benefactor_id' => $batch->benefactor_id,
                    'funding_type_id' => $batch->funding_type_id ?? $plan->funding_type_id,
                    'coverage_start_date' => $coverageStart->toDateString(),
                    'coverage_end_date' => $coverageEnd?->toDateString(),
                    'status' => 1,
                    'approval_date' => now(),
                    'approved_by' => auth()->id(),
                ]);
                $row->update(['enrollee_id' => $enrollee->id, 'status' => 'covered']);
                $count++;
            }

            $old = $batch->toArray();
            $batch->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $this->audit->record($batch, 'payroll_batch_approved', "Payroll batch {$batch->batch_code} approved.", $old, $batch->fresh()->toArray());
        });

        return $count;
    }

    private function uniquePin(): string
    {
        do {
            $pin = (string) random_int(100000000000, 999999999999);
        } while (PremiumPin::where('pin', $pin)->exists());

        return $pin;
    }

    private function assertPinUsableForSale(PremiumPin $pin): void
    {
        if ($pin->isExpired() || $pin->status !== PremiumPin::STATUS_GENERATED) {
            throw new InvalidArgumentException('Premium PIN cannot be sold because it is expired, cancelled, sold, or used.');
        }
    }
}
