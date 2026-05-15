<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Enrollee;
use App\Models\EnrolleeFacilityTransfer;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $approver;
    private Facility $from;
    private Facility $to;
    private Enrollee $enrollee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->from     = Facility::factory()->create(['accreditation_status' => 'active']);
        $this->to       = Facility::factory()->create(['accreditation_status' => 'active']);
        $this->enrollee = Enrollee::factory()->create(['facility_id' => $this->from->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_request_facility_transfer(): void
    {
        $response = $this->postJson("/api/enrollees/{$this->enrollee->id}/transfer", [
            'target_facility_id' => $this->to->id,
            'transfer_reason'    => 'Enrollee relocated',
            'effective_date'     => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('enrollee_facility_transfers', [
            'enrollee_id'    => $this->enrollee->id,
            'to_facility_id' => $this->to->id,
            'status'         => 'pending',
        ]);
    }

    public function test_transfer_blocked_when_enrollee_has_active_admission(): void
    {
        Admission::factory()->create([
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->from->id,
            'status'      => 'active',
        ]);

        $response = $this->postJson("/api/enrollees/{$this->enrollee->id}/transfer", [
            'target_facility_id' => $this->to->id,
            'transfer_reason'    => 'Relocation',
            'effective_date'     => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_br06_submitter_cannot_approve_own_transfer_request(): void
    {
        // Requestor submits the transfer
        $transfer = EnrolleeFacilityTransfer::create([
            'enrollee_id'      => $this->enrollee->id,
            'from_facility_id' => $this->from->id,
            'to_facility_id'   => $this->to->id,
            'transfer_reason'  => 'Relocation',
            'effective_date'   => now()->addDays(7),
            'status'           => 'pending',
            'transferred_by'   => $this->user->id, // same user
        ]);

        // Same user tries to approve
        $response = $this->postJson("/api/enrollees/transfers/{$transfer->id}/approve");

        $response->assertStatus(403)->assertJsonPath('success', false);
    }
}
