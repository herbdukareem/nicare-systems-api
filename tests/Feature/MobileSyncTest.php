<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\MobileSyncRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileSyncTest extends TestCase
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

    public function test_can_push_sync_batch(): void
    {
        $response = $this->postJson('/api/mobile-sync/push', [
            'device_id' => 'DEVICE-001',
            'records'   => [
                [
                    'first_name'    => 'Amina',
                    'last_name'     => 'Bello',
                    'date_of_birth' => '1990-03-15',
                    'gender'        => 'Female',
                    'phone'         => '08012345678',
                    'facility_id'   => $this->facility->id,
                ],
            ],
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('mobile_sync_records', ['status' => 'pending']);
    }

    public function test_can_check_sync_batch_status(): void
    {
        $batchId = Str::uuid()->toString();

        MobileSyncRecord::create([
            'sync_batch_id' => $batchId,
            'device_id'     => 'DEVICE-001',
            'payload'       => ['first_name' => 'Test'],
            'status'        => 'pending',
        ]);

        $response = $this->getJson("/api/mobile-sync/status/{$batchId}");

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_can_list_failed_sync_records(): void
    {
        MobileSyncRecord::create([
            'sync_batch_id' => Str::uuid()->toString(),
            'device_id'     => 'DEVICE-002',
            'payload'       => ['first_name' => 'Fail'],
            'status'        => 'failed',
            'error_message' => 'Duplicate NIN detected',
        ]);

        $response = $this->getJson('/api/mobile-sync/failed');

        $response->assertOk()->assertJsonPath('success', true);
    }
}
