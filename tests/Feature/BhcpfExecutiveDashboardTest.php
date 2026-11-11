<?php

namespace Tests\Feature;

use App\Models\Benefactor;
use App\Models\EnrollmentPhase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BhcpfExecutiveDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_lists_and_selects_phases_without_bhcpf_targets(): void
    {
        $benefactor = Benefactor::create(['name' => 'Dashboard phase test']);
        $phase = EnrollmentPhase::create([
            'name' => 'Untargeted phase',
            'benefactor_id' => $benefactor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 0,
            'is_current' => false,
        ]);

        $role = Role::create(['name' => 'executive-'.uniqid(), 'label' => 'Executive dashboard test']);
        $role->permissions()->sync([
            Permission::firstOrCreate(['name' => 'dashboard.bhcpf_executive.view'], ['label' => 'View BHCPF executive dashboard'])->id,
        ]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/executive/bhcpf-vulnerable?enrollment_phase_id='.$phase->id.'&date_from='.now()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.campaign.id', $phase->id)
            ->assertJsonPath('data.filters.enrollment_phase_id', $phase->id)
            ->assertJsonPath('data.summary.overall_target', 0);

        $this->assertContains($phase->id, array_column($response->json('data.enrollment_phases'), 'id'));
    }
}
