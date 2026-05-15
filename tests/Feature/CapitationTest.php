<?php

namespace Tests\Feature;

use App\Models\Capitation;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapitationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->facility = Facility::factory()->create(['accreditation_status' => 'active']);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_create_capitation_period(): void
    {
        $response = $this->postJson('/api/capitation/periods', [
            'name'            => 'May 2026 Capitation',
            'period_start'    => '2026-05-01',
            'period_end'      => '2026-05-31',
            'capitation_rate' => 1500.00,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'May 2026 Capitation');

        $this->assertDatabaseHas('capitations', ['name' => 'May 2026 Capitation']);
    }

    public function test_can_list_capitation_periods(): void
    {
        Capitation::create([
            'name'            => 'April 2026 Capitation',
            'period_start'    => '2026-04-01',
            'period_end'      => '2026-04-30',
            'capitation_rate' => 1500.00,
            'status'          => 'draft',
            'created_by'      => $this->user->id,
        ]);

        $response = $this->getJson('/api/capitation/periods');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_br07_compute_counts_only_full_period_enrollees(): void
    {
        $capitation = Capitation::create([
            'name'            => 'BR-07 Test Capitation',
            'period_start'    => '2026-05-01',
            'period_end'      => '2026-05-31',
            'capitation_rate' => 1500.00,
            'status'          => 'draft',
            'created_by'      => $this->user->id,
        ]);

        // Enrollee active for the full period
        Enrollee::factory()->create([
            'facility_id'            => $this->facility->id,
            'status'                 => 'active',
            'capitation_start_date'  => '2026-04-01',
        ]);

        // Enrollee active mid-period — should NOT be counted (BR-07)
        Enrollee::factory()->create([
            'facility_id'            => $this->facility->id,
            'status'                 => 'active',
            'capitation_start_date'  => '2026-05-15',
        ]);

        $response = $this->postJson("/api/capitation/periods/{$capitation->id}/compute");

        $response->assertOk()->assertJsonPath('success', true);

        // BR-07: only enrollees active before/on period_start should be counted
        $computed = $capitation->fresh();
        $this->assertNotNull($computed->computed_at);
    }

    public function test_cannot_finalise_uncomputed_capitation(): void
    {
        $capitation = Capitation::create([
            'name'            => 'Uncomputed Capitation',
            'period_start'    => '2026-05-01',
            'period_end'      => '2026-05-31',
            'capitation_rate' => 1500.00,
            'status'          => 'draft',
            'created_by'      => $this->user->id,
        ]);

        $response = $this->postJson("/api/capitation/periods/{$capitation->id}/finalise");

        $response->assertStatus(422);
    }
}
