<?php

namespace Tests\Feature;

use App\Models\EnrolleeImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrolleeImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
        Storage::fake('local');
        Queue::fake();
    }

    public function test_can_upload_enrollee_import_file(): void
    {
        $csv = UploadedFile::fake()->createWithContent(
            'enrollees.csv',
            "first_name,last_name,date_of_birth,gender,phone,facility_id\n" .
            "John,Doe,1990-01-01,Male,08011111111,1\n"
        );

        $response = $this->postJson('/api/enrollees/import', [
            'file' => $csv,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('enrollee_import_batches', ['status' => 'queued']);
    }

    public function test_can_check_import_batch_status(): void
    {
        $batch = EnrolleeImportBatch::create([
            'filename'     => 'test.csv',
            'total_rows'   => 5,
            'processed'    => 3,
            'success_count'=> 3,
            'failure_count'=> 0,
            'status'       => 'processing',
            'uploaded_by'  => $this->user->id,
        ]);

        $response = $this->getJson("/api/enrollees/import/{$batch->id}");

        $response->assertOk()
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.status', 'processing');
    }

    public function test_can_download_import_template(): void
    {
        $response = $this->get('/api/enrollees/import-template');

        $response->assertOk();
        // Response should be a CSV or Excel download
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition', '')
        );
    }
}
