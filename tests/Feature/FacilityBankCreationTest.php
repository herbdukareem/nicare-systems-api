<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Facility;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FacilityBankCreationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_facility_creator_can_add_a_bank_and_use_it_for_a_new_facility(): void
    {
        $this->signInWithPermission('setup.facility.create');

        $response = $this->postJson('/api/banks', [
            'name' => 'New Facility Bank',
            'code' => '000FAC',
            'sort_code' => '000123456',
        ])->assertCreated()
            ->assertJsonPath('data.code', '000FAC')
            ->assertJsonPath('data.sort_code', '000123456');

        $bankId = $response->json('data.id');
        $this->assertDatabaseHas('banks', ['id' => $bankId, 'name' => 'New Facility Bank', 'status' => 1]);
        $this->getJson('/api/banks')->assertOk()->assertJsonFragment(['id' => $bankId, 'name' => 'New Facility Bank']);

        $this->postJson('/api/facilities', Facility::factory()->make()->getAttributes() + [
            'account_detail' => [
                'bank_id' => $bankId,
                'account_name' => 'New Facility Account',
                'account_number' => '0012345678',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.account_detail.bank.id', $bankId)
            ->assertJsonPath('data.account_detail.bank.name', 'New Facility Bank');
    }

    public function test_facility_view_or_update_permission_does_not_allow_bank_creation(): void
    {
        foreach (['setup.facility.view', 'setup.facility.update'] as $permission) {
            $this->signInWithPermission($permission);
            $this->postJson('/api/banks', ['name' => 'Not Allowed Bank', 'code' => 'NOTALLOWED'])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('banks', ['code' => 'NOTALLOWED']);
    }

    public function test_unauthenticated_users_cannot_create_banks(): void
    {
        $this->postJson('/api/banks', ['name' => 'Not Allowed Bank', 'code' => 'NOTALLOWED'])
            ->assertUnauthorized();
    }

    public function test_bank_creation_validates_required_fields_and_duplicate_codes(): void
    {
        $this->signInWithPermission('setup.facility.create');
        $this->postJson('/api/banks', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);

        $bank = Bank::create(['name' => 'Existing Bank', 'code' => 'DUPFAC']);
        $this->postJson('/api/banks', ['name' => 'Duplicate Code Bank', 'code' => $bank->code])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseMissing('banks', ['name' => 'Duplicate Code Bank']);

        $this->postJson('/api/banks', ['name' => 'Bank Without Sort Code', 'code' => 'NOSORTFAC'])
            ->assertCreated()->assertJsonPath('data.sort_code', null);
    }

    private function signInWithPermission(string $permissionName): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'facility-bank-test-' . $permissionName]);
        $permission = Permission::firstOrCreate(['name' => $permissionName]);
        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $user->forceFill(['current_role_id' => $role->id])->save();

        $this->actingAs($user, 'sanctum');
    }
}
