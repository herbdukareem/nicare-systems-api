<?php

namespace Tests\Feature;

use App\Models\AccountDetail;
use App\Models\Bank;
use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use App\Services\FacilityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FacilityBankDetailsTest extends TestCase
{
    use DatabaseTransactions;

    private Bank $bank;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin']);
        $user->roles()->sync([$role->id]);
        $user->forceFill(['current_role_id' => $role->id])->save();
        $this->actingAs($user, 'sanctum');

        $this->bank = Bank::create(['name' => 'Facility Test Bank', 'code' => '001', 'sort_code' => '000123456', 'status' => 1]);
        $this->facility = Facility::factory()->create();
    }

    public function test_creating_a_facility_saves_and_returns_its_owned_bank_account(): void
    {
        $response = $this->postJson('/api/facilities', $this->payload() + ['account_detail' => $this->bankDetails()]);
        $response->assertCreated()
            ->assertJsonPath('data.account_detail.account_number', '0012345678')
            ->assertJsonPath('data.account_detail.account_name', 'Facility Account')
            ->assertJsonPath('data.account_detail.bank_id', $this->bank->id)
            ->assertJsonPath('data.account_detail.bank.name', $this->bank->name)
            ->assertJsonPath('data.account_detail.bank.sort_code', '000123456');
        $this->assertDatabaseHas('account_details', [
            'id' => $response->json('data.account_detail.id'),
            'accountable_type' => (new Facility)->getMorphClass(),
            'accountable_id' => $response->json('data.id'),
            'account_number' => '0012345678',
        ]);
        $this->assertDatabaseHas('facilities', [
            'id' => $response->json('data.id'), 'account_detail_id' => $response->json('data.account_detail.id'),
        ]);
    }

    public function test_bank_details_remain_optional_on_create(): void
    {
        $count = AccountDetail::count();
        $this->postJson('/api/facilities', $this->payload())->assertCreated()->assertJsonPath('data.account_detail', null);
        $this->assertSame($count, AccountDetail::count());
    }

    public function test_existing_facility_can_add_bank_details(): void
    {
        $this->putJson($this->url(), ['account_detail' => $this->bankDetails()])
            ->assertOk()->assertJsonPath('data.account_detail.account_number', '0012345678');
        $this->assertSame($this->facility->id, (int) $this->facility->fresh()->accountDetail->accountable_id);
    }

    public function test_edit_updates_the_existing_account_and_loads_it_for_prefilling(): void
    {
        $account = $this->attachAccount();
        $account->update(['is_verified' => true, 'verified_at' => now(), 'verification_method' => 'manual']);
        $this->putJson($this->url(), ['account_detail' => array_replace($this->bankDetails(), [
            'account_name' => 'Updated Facility Account', 'account_number' => '0098765432', 'account_type' => 'current',
        ])])->assertOk()->assertJsonPath('data.account_detail.id', $account->id);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.account_detail.bank.id', $this->bank->id)
            ->assertJsonPath('data.account_detail.account_number', '0098765432')
            ->assertJsonPath('data.account_detail.account_type', 'current');
        $this->assertDatabaseHas('account_details', [
            'id' => $account->id, 'account_name' => 'Updated Facility Account',
            'is_verified' => false, 'verified_at' => null, 'verification_method' => null,
        ]);
        $this->assertSame(1, AccountDetail::where('accountable_type', $this->facility->getMorphClass())
            ->where('accountable_id', $this->facility->id)->count());
    }

    public function test_omitting_bank_details_preserves_the_existing_account_for_older_clients(): void
    {
        $account = $this->attachAccount();
        $this->putJson($this->url(), ['name' => 'Renamed Facility'])->assertOk()
            ->assertJsonPath('data.account_detail.id', $account->id)
            ->assertJsonPath('data.account_detail.account_number', '0012345678');
    }

    public function test_incomplete_or_invalid_bank_details_fail_without_saving_the_facility(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/facilities', $payload + ['account_detail' => ['bank_id' => $this->bank->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['account_detail.account_name', 'account_detail.account_number']);
        $this->assertDatabaseMissing('facilities', ['hcp_code' => $payload['hcp_code']]);
        $this->putJson($this->url(), ['name' => 'Must Not Save', 'account_detail' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('account_detail');
        $this->putJson($this->url(), ['account_detail' => array_replace($this->bankDetails(), ['bank_id' => -1])])
            ->assertUnprocessable()->assertJsonValidationErrors('account_detail.bank_id');
        $this->assertNotSame('Must Not Save', $this->facility->fresh()->name);
        $this->assertNull($this->facility->fresh()->account_detail_id);
    }

    public function test_clearing_details_detaches_the_account_without_deleting_historical_data(): void
    {
        $account = $this->attachAccount();
        $this->putJson($this->url(), ['account_detail' => null])->assertOk()->assertJsonPath('data.account_detail', null);
        $this->assertNull($this->facility->fresh()->account_detail_id);
        $this->assertDatabaseHas('account_details', ['id' => $account->id, 'account_number' => '0012345678']);
    }

    public function test_editing_a_shared_legacy_account_does_not_change_another_facility(): void
    {
        $account = $this->attachAccount();
        $other = Facility::factory()->create(['account_detail_id' => $account->id]);
        $response = $this->putJson($this->url(), ['account_detail' => array_replace($this->bankDetails(), [
            'account_number' => '0000000001',
        ])])->assertOk()->assertJsonPath('data.account_detail.account_number', '0000000001');
        $this->assertNotSame($account->id, $response->json('data.account_detail.id'));
        $this->assertSame('0012345678', $other->fresh()->accountDetail->account_number);
        $this->assertSame($account->id, $other->fresh()->account_detail_id);
    }

    public function test_account_save_failure_rolls_back_the_facility_create_and_update(): void
    {
        $service = app(FacilityService::class);
        $payload = $this->payload();
        $invalidDetails = array_replace($this->bankDetails(), ['bank_id' => -1]);
        try {
            $service->create($payload + ['account_detail' => $invalidDetails]);
            $this->fail('Expected the bank foreign key to reject the account.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('facilities', ['hcp_code' => $payload['hcp_code']]);
        }
        $originalName = $this->facility->name;
        try {
            $service->update($this->facility, ['name' => 'Must Roll Back', 'account_detail' => $invalidDetails]);
            $this->fail('Expected the bank foreign key to reject the account.');
        } catch (QueryException) {
            $this->assertSame($originalName, $this->facility->fresh()->name);
            $this->assertNull($this->facility->fresh()->account_detail_id);
        }
    }

    public function test_bank_details_use_the_existing_facility_permissions(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/facilities', $this->payload() + ['account_detail' => $this->bankDetails()])->assertForbidden();
        $this->putJson($this->url(), ['account_detail' => $this->bankDetails()])->assertForbidden();
    }

    private function attachAccount(): AccountDetail
    {
        $account = AccountDetail::create($this->bankDetails() + [
            'accountable_type' => $this->facility->getMorphClass(), 'accountable_id' => $this->facility->id,
        ]);
        $this->facility->accountDetail()->associate($account);
        $this->facility->save();

        return $account;
    }

    private function payload(): array
    {
        return Facility::factory()->make()->getAttributes();
    }

    private function bankDetails(): array
    {
        return ['bank_id' => $this->bank->id, 'account_name' => 'Facility Account', 'account_number' => '0012345678', 'account_type' => 'savings'];
    }

    private function url(): string
    {
        return '/api/facilities/' . $this->facility->id;
    }
}
