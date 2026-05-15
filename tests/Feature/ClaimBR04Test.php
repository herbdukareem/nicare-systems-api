<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Claim;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Referral;
use App\Models\User;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ClaimBR04Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Facility $facility;
    private Enrollee $enrollee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->facility = Facility::factory()->create(['accreditation_status' => 'active']);
        $this->enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_br04_claim_creation_blocked_when_referral_utn_expired(): void
    {
        // Create an expired referral
        $referral = Referral::factory()->create([
            'enrollee_id'          => $this->enrollee->id,
            'receiving_facility_id' => $this->facility->id,
            'status'               => 'approved',
            'utn'                  => 'UTN-EXPIRED-001',
            'utn_validated'        => true,
            'valid_until'          => now()->subDays(10), // expired 10 days ago
        ]);

        $admission = Admission::factory()->create([
            'referral_id' => $referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'status'      => 'discharged',
        ]);

        $service = app(ClaimProcessingService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/expired/i');

        $service->createClaimWithLineItems($admission->id, now()->toDateString(), []);
    }

    public function test_br04_claim_creation_succeeds_when_utn_valid(): void
    {
        $referral = Referral::factory()->create([
            'enrollee_id'          => $this->enrollee->id,
            'receiving_facility_id' => $this->facility->id,
            'status'               => 'approved',
            'utn'                  => 'UTN-VALID-001',
            'utn_validated'        => true,
            'valid_until'          => now()->addDays(30), // still valid
        ]);

        $admission = Admission::factory()->create([
            'referral_id' => $referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'status'      => 'discharged',
        ]);

        $service = app(ClaimProcessingService::class);

        // Should not throw — UTN is valid
        $claim = $service->createClaimWithLineItems($admission->id, now()->toDateString(), []);

        $this->assertInstanceOf(Claim::class, $claim);
        $this->assertEquals('DRAFT', $claim->status);
    }
}
