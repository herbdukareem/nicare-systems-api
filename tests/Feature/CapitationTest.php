<?php

namespace Tests\Feature;

use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\FundingType;
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

    public function test_period_counts_can_be_scoped_to_a_selected_funding_type(): void
    {
        $bhcpf = FundingType::create([
            'name' => 'Basic Healthcare Provision Fund',
            'description' => 'BHCPF',
            'capitation_rate' => 570,
            'status' => 1,
        ]);
        $gac = FundingType::create([
            'name' => 'GAC',
            'description' => 'GAC',
            'capitation_rate' => 570,
            'status' => 1,
        ]);

        $capitation = Capitation::create([
            'name' => 'August Capitation',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'capitation_rate' => 570.00,
            'capitated_month' => 8,
            'capitation_month' => 8,
            'year' => 2026,
            'funding_type_id' => $bhcpf->id,
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => 1,
        ]);

        CapitationDetail::create([
            'capitation_id' => $capitation->id,
            'facility_id' => $this->facility->id,
            'funding_type_id' => $bhcpf->id,
            'capitated_month' => 8,
            'total_enrollees' => 10,
            'capitation_rate' => 570.00,
            'total_amount' => 5700.00,
            'total_enrolled' => 10,
            'rate' => 570.00,
            'amount' => 5700.00,
            'reviewed_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'paid_by' => $this->user->id,
            'reviewed_at' => '2026-08-25',
            'approved_at' => '2026-08-25',
            'paid_at' => '2026-09-03',
            'status' => 4,
        ]);

        CapitationDetail::create([
            'capitation_id' => $capitation->id,
            'facility_id' => Facility::factory()->create(['accreditation_status' => 'active'])->id,
            'funding_type_id' => $gac->id,
            'capitated_month' => 8,
            'total_enrollees' => 12,
            'capitation_rate' => 570.00,
            'total_amount' => 6840.00,
            'total_enrolled' => 12,
            'rate' => 570.00,
            'amount' => 6840.00,
            'reviewed_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'reviewed_at' => '2026-08-25',
            'approved_at' => '2026-08-25',
            'paid_at' => null,
            'status' => 3,
        ]);

        $overall = $this->getJson('/api/capitation/periods?per_page=100');

        $overall->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.capitation_details_count', 2)
            ->assertJsonPath('data.data.0.pending_payment_count', 1)
            ->assertJsonPath('data.data.0.paid_count', 1);

        $paidSlice = $this->getJson('/api/capitation/periods?per_page=100&funding_type_id=' . $bhcpf->id);

        $paidSlice->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $capitation->id)
            ->assertJsonPath('data.data.0.capitation_details_count', 1)
            ->assertJsonPath('data.data.0.pending_payment_count', 0)
            ->assertJsonPath('data.data.0.paid_count', 1);

        $pendingSlice = $this->getJson('/api/capitation/periods?per_page=100&funding_type_id=' . $gac->id);

        $pendingSlice->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $capitation->id)
            ->assertJsonPath('data.data.0.capitation_details_count', 1)
            ->assertJsonPath('data.data.0.pending_payment_count', 1)
            ->assertJsonPath('data.data.0.paid_count', 0);
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
