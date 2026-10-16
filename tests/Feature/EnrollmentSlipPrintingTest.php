<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\EnrollmentSlipPrintBatch;
use App\Models\Facility;
use App\Models\Lga;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EnrollmentSlipPrintingService;
use App\Services\EnrollmentSlipCollectionListService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentSlipPrintingTest extends TestCase
{
    use DatabaseTransactions;

    private EnrollmentSlipPrintingService $printing;
    private User $printer;
    private array $enrolleeScopes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enrolleeScopes = Enrollee::getAllGlobalScopes();
        $baselineId = Enrollee::max('id') ?? 0;
        Enrollee::addGlobalScope('slip-test-fixtures', fn ($q) => $q->where('enrollees.id', '>', $baselineId));
        Storage::fake('local');
        $this->printing = app(EnrollmentSlipPrintingService::class);
        $this->printer = $this->printer();
        $this->actingAs($this->printer, 'sanctum');
    }

    protected function tearDown(): void
    {
        Enrollee::setAllGlobalScopes($this->enrolleeScopes);
        parent::tearDown();
    }

    public function test_default_queue_only_includes_approved_slips_without_a_recorded_generation(): void
    {
        $first = $this->approved();
        $printed = $this->approved(['slip_first_generated_at' => now(), 'slip_last_generated_at' => now(), 'slip_generation_count' => 1]);
        Enrollee::factory()->create(['status' => 0, 'approval_date' => null]);
        Enrollee::factory()->create(['status' => 1, 'approval_date' => null]);
        $this->getJson('/api/enrollees/slip-printing/queue')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $first->id);
        $this->getJson('/api/enrollees/slip-printing/queue?printing_status=generated')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $printed->id);
        $this->assertNull($first->fresh()->slip_first_generated_at);
        $this->assertSame(0, EnrollmentSlipPrintBatch::count());
    }

    public function test_preparation_freezes_membership_and_later_approvals_remain_outstanding(): void
    {
        $olderPending = Enrollee::factory()->create(['status' => 0, 'approval_date' => null]);
        $first = $this->approved();
        $batch = $this->prepare();
        $this->assertSame([$first->id], $batch->items()->pluck('enrollee_id')->all());
        $this->assertNull($first->fresh()->slip_first_generated_at);
        $this->assertSame(0, $first->fresh()->slip_generation_count);
        $olderPending->update(['status' => 1, 'approval_date' => now()]);
        $new = $this->approved();
        $part = $this->render($batch);
        $this->assertSame('PDF '.$first->id, $part['bytes']);
        $this->assertNull($first->fresh()->slip_first_generated_at, 'Part generation alone must not mark a slip.');
        $this->printing->complete($batch->id, $this->printer->id);
        $this->assertEqualsCanonicalizing([$olderPending->id, $new->id], $this->printing->query([])->pluck('id')->all());
    }

    public function test_completion_and_part_retries_are_idempotent_and_reprints_preserve_first_date(): void
    {
        $enrollee = $this->approved();
        $batch = $this->prepare();
        $this->render($batch);
        $this->printing->part($batch->id, $this->printer->id, 1, fn () => throw new \RuntimeException('Must reuse saved PDF'));
        $this->printing->complete($batch->id, $this->printer->id);
        $firstDate = $enrollee->fresh()->slip_first_generated_at;
        $this->printing->complete($batch->id, $this->printer->id);
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
        $this->assertSame(1, AuditTrail::where('auditable_id', $enrollee->id)->where('action', 'enrollment_slip_generated')->count());
        $this->travel(1)->hour();
        $reprint = $this->prepare(['printing_status' => 'generated']);
        $this->render($reprint);
        $this->assertTrue($reprint->fresh()->expires_at->isFuture(), 'Saving a PDF must not change the batch expiry.');
        $this->printing->complete($reprint->id, $this->printer->id);
        $enrollee->refresh();
        $this->assertSame(2, $enrollee->slip_generation_count);
        $this->assertTrue($firstDate->equalTo($enrollee->slip_first_generated_at));
        $this->assertTrue($enrollee->slip_last_generated_at->greaterThan($firstDate));
        $this->assertSame($this->printer->id, $enrollee->slip_last_generated_by);
        $this->assertSame($reprint->id, $enrollee->slip_last_batch_id);
        $this->assertSame(1, AuditTrail::where('auditable_id', $enrollee->id)->where('action', 'enrollment_slip_reprinted')->count());
    }

    public function test_an_incomplete_or_failed_batch_never_marks_any_slips(): void
    {
        $enrollees = Enrollee::factory()->count(41)->create(['status' => 1, 'approval_date' => now()]);
        $batch = $this->prepare();
        try {
            $this->printing->part($batch->id, $this->printer->id, 1, fn () => throw new \RuntimeException('PDF failed'));
            $this->fail('Rendering should fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('PDF failed', $error->getMessage());
        }
        $this->assertSame([], $batch->fresh()->parts);
        $this->render($batch);
        $this->postJson('/api/enrollees/slip-printing/batches/'.$batch->id.'/complete')->assertStatus(409);
        $this->assertSame(0, Enrollee::whereKey($enrollees->modelKeys())->whereNotNull('slip_first_generated_at')->count());
        $this->render($batch, 2);
        $this->postJson('/api/enrollees/slip-printing/batches/'.$batch->id.'/complete')->assertOk();
        $this->assertSame(41, Enrollee::whereKey($enrollees->modelKeys())->where('slip_generation_count', 1)->count());
    }

    public function test_same_request_reuses_the_batch_and_another_exercise_cannot_claim_its_slips(): void
    {
        $enrollee = $this->approved();
        $request = ['request_id' => (string) Str::uuid()];
        $batch = $this->prepare($request);
        $this->assertSame($batch->id, $this->prepare($request)->id);
        $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'enrollee_ids' => [$enrollee->id]])->assertStatus(409);
        $other = $this->printer();
        $this->actingAs($other, 'sanctum')->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'identifier' => $enrollee->enrollee_id])->assertStatus(409);
        $this->assertSame(1, EnrollmentSlipPrintBatch::count());
    }

    public function test_releasing_and_expiring_batches_returns_their_slips_to_the_queue(): void
    {
        $enrollee = $this->approved();
        $batch = $this->prepare();
        $this->assertSame(0, $this->printing->query([])->count());
        $this->printing->cancel($batch->id, $this->printer->id);
        $this->assertSame(1, $this->printing->query([])->count());
        $this->assertNull($enrollee->fresh()->slip_reserved_batch_id);
        $next = $this->prepare();
        $this->travel(121)->minutes();
        $this->assertSame(1, $this->printing->query([])->count());
        $this->postJson('/api/enrollees/slip-printing/batches/'.$next->id.'/complete')->assertGone();
        $this->artisan('enrollment-slips:prune')->assertSuccessful();
        $this->assertSame('expired', $next->fresh()->status);
        $this->assertNull($enrollee->fresh()->slip_first_generated_at);
    }

    public function test_a_batch_cannot_be_read_resumed_completed_or_cancelled_by_another_user(): void
    {
        $this->approved();
        $batch = $this->prepare();
        $this->render($batch);
        $this->actingAs($this->printer(), 'sanctum');
        $base = '/api/enrollees/slip-printing/batches/'.$batch->id;
        $this->getJson($base)->assertNotFound();
        $this->postJson($base.'/complete')->assertNotFound();
        $this->postJson($base.'/cancel')->assertNotFound();
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $batch->id, 'part' => 1])->assertNotFound();
        $this->getJson('/api/enrollees/slip-printing/batches')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_batch_access_respects_enrollee_model_scopes_even_for_the_batch_owner(): void
    {
        $enrollee = $this->approved();
        $batch = $this->prepare();
        $scopes = Enrollee::getAllGlobalScopes();
        try {
            Enrollee::addGlobalScope('test-business-isolation', fn ($q) => $q->where('enrollees.id', '<>', $enrollee->id));
            $this->getJson('/api/enrollees/slip-printing/queue')->assertOk()->assertJsonPath('data.total', 0);
            $this->getJson('/api/enrollees/slip-printing/batches/'.$batch->id)->assertForbidden();
            $this->postJson('/api/enrollees/slip-printing/batches/'.$batch->id.'/complete')->assertForbidden();
            $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $batch->id, 'part' => 1])->assertForbidden();
            $this->getJson('/api/enrollees/slip-printing/batches')->assertOk()->assertJsonPath('data.total', 0);
        } finally {
            Enrollee::setAllGlobalScopes($scopes);
        }
    }

    public function test_selected_ids_must_all_match_the_filters_and_invalid_identifiers_are_rejected(): void
    {
        $approved = $this->approved();
        $pending = Enrollee::factory()->create(['status' => 0, 'approval_date' => null]);
        $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'enrollee_ids' => [$approved->id, $pending->id]])->assertStatus(409);
        $this->assertNull($approved->fresh()->slip_reserved_batch_id);
        $this->assertSame(0, EnrollmentSlipPrintBatch::count());
        $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'printing_status' => 'bogus'])->assertUnprocessable();
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => (string) Str::uuid(), 'part' => 0])->assertUnprocessable();
    }

    public function test_single_lookup_finds_previous_slips_and_rejects_ambiguous_nins(): void
    {
        $enrollee = $this->approved(['slip_first_generated_at' => now(), 'slip_generation_count' => 1]);
        $this->getJson('/api/enrollees/slip-printing/queue?identifier='.$enrollee->enrollee_id)->assertOk()->assertJsonPath('data.total', 1);
        $batch = $this->prepare(['identifier' => $enrollee->enrollee_id]);
        $this->assertSame(1, $batch->enrollee_count);
        Enrollee::factory()->count(2)->create(['nin' => '11111111111']);
        $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'identifier' => '11111111111'])->assertUnprocessable();
    }

    public function test_saved_copy_download_does_not_increment_count_and_pruning_preserves_history(): void
    {
        $enrollee = $this->approved();
        $batch = $this->prepare();
        $this->render($batch);
        $this->printing->complete($batch->id, $this->printer->id);
        $this->render($batch);
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
        $this->travel(8)->days();
        $this->artisan('enrollment-slips:prune')->assertSuccessful();
        Storage::disk('local')->assertMissing('slip-printing/'.$batch->id.'/part_1.pdf');
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $batch->id, 'part' => 1])->assertGone();
    }

    public function test_the_http_flow_renders_a_real_pdf_and_only_marks_after_completion(): void
    {
        $enrollee = $this->approved(['image_url' => null]);
        $prepare = $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true, 'identifier' => $enrollee->enrollee_id])->assertOk();
        $token = $prepare->json('data.token');
        $response = $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $token, 'part' => 1])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertNull($enrollee->fresh()->slip_first_generated_at);
        $this->postJson('/api/enrollees/slip-printing/batches/'.$token.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
    }

    public function test_users_without_print_or_view_permissions_cannot_access_the_workflow(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/enrollees/slip-printing/queue')->assertForbidden();
        $this->getJson('/api/enrollees/slip-printing/batches')->assertForbidden();
        $this->postJson('/api/enrollees/bulk-enrollment-slip', ['tracked' => true])->assertForbidden();
    }

    public function test_selected_rows_freeze_only_the_requested_subset_and_keep_parts_correct_across_insert_chunks(): void
    {
        $enrollees = Enrollee::factory()->count(501)->create(['status' => 1, 'approval_date' => now()]);
        $batch = $this->prepare();
        $this->assertSame(501, $batch->items()->count());
        $this->assertSame(13, $batch->items()->where('enrollee_id', $enrollees->last()->id)->value('part'));
        $this->assertSame(40, $batch->items()->where('part', 1)->count());
        $this->assertSame(21, $batch->items()->where('part', 13)->count());
        $this->printing->cancel($batch->id, $this->printer->id);
        $subset = [$enrollees[0]->id, $enrollees[500]->id];
        $selected = $this->prepare(['enrollee_ids' => $subset]);
        $this->assertSame($subset, $selected->items()->orderBy('enrollee_id')->pluck('enrollee_id')->all());
        $this->render($selected);
        $this->printing->complete($selected->id, $this->printer->id);
        $this->assertSame(499, $this->printing->query([])->count());
    }

    public function test_an_expired_batch_cannot_complete_after_another_user_reclaims_its_slips(): void
    {
        $enrollee = $this->approved();
        $original = $this->prepare();
        $this->render($original);
        $this->travel(121)->minutes();
        $other = $this->printer();
        $next = $this->printing->prepare([], $other->id);
        $this->postJson('/api/enrollees/slip-printing/batches/'.$original->id.'/complete')->assertGone();
        $this->printing->cancel($original->id, $this->printer->id);
        $this->assertSame($next->id, $enrollee->fresh()->slip_reserved_batch_id, 'Releasing an old batch must not release the new owner\'s reservation.');
        $this->printing->part($next->id, $other->id, 1, fn () => 'PDF');
        $this->printing->complete($next->id, $other->id);
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
    }

    public function test_legacy_single_and_bulk_clients_are_tracked_without_a_completion_call(): void
    {
        $single = $this->approved(['image_url' => null]);
        $this->get('/api/enrollees/bulk-enrollment-slip?identifier='.$single->enrollee_id)
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $single->fresh()->slip_generation_count);
        $bulk = $this->approved(['image_url' => null]);
        $prepare = $this->postJson('/api/enrollees/bulk-enrollment-slip', ['enrollee_ids' => [$bulk->id]])->assertOk();
        $token = $prepare->json('data.token');
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $token, 'part' => 1])->assertOk();
        $this->assertSame(1, $bulk->fresh()->slip_generation_count);
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $token, 'part' => 1])->assertOk();
        $this->assertSame(1, $bulk->fresh()->slip_generation_count);
    }

    public function test_encrypted_tokens_from_before_the_upgrade_are_tracked_and_retry_safely(): void
    {
        $enrollee = $this->approved(['image_url' => null]);
        $token = Crypt::encryptString(json_encode([
            'requested_by' => $this->printer->id, 'filters' => ['facility_id' => $enrollee->facility_id],
            'snapshot_max_id' => $enrollee->id, 'enrollee_count' => 1,
            'generated_at' => now()->timestamp, 'expires_at' => now()->addHour()->timestamp,
        ]));
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $token, 'part' => 1, 'cursor' => 0])->assertOk();
        $this->postJson('/api/enrollees/bulk-enrollment-slip/part', ['token' => $token, 'part' => 1, 'cursor' => 0])->assertOk();
        $this->assertSame(1, $enrollee->fresh()->slip_generation_count);
    }

    public function test_collection_lists_only_include_generated_slips_and_do_not_change_print_history(): void
    {
        $first = $this->approved(['slip_first_generated_at' => now(), 'slip_generation_count' => 2]);
        $unprinted = $this->approved();
        $otherFacility = Facility::factory()->create();
        $this->approved(['facility_id' => $otherFacility->id, 'slip_first_generated_at' => now(), 'slip_generation_count' => 1]);
        $lists = app(EnrollmentSlipCollectionListService::class);
        $rows = $lists->rows(['facility_id' => $first->facility_id, 'printing_status' => 'outstanding'], $this->printer->id);
        $this->assertSame([$first->id], $rows->modelKeys());
        $this->getJson('/api/enrollees/slip-printing/collection-list?identifier='.$unprinted->enrollee_id)->assertNotFound();
        $this->getJson('/api/enrollees/slip-printing/collection-list?enrollee_ids[]='.$first->id.'&enrollee_ids[]='.$unprinted->id)->assertUnprocessable();
        $this->assertSame(2, $first->fresh()->slip_generation_count);
        $this->assertNull($first->fresh()->slip_reserved_batch_id);
        $this->assertNull($unprinted->fresh()->slip_first_generated_at);
        $this->assertSame(0, EnrollmentSlipPrintBatch::where('user_id', $this->printer->id)->count());
    }

    public function test_batch_collection_lists_require_completion_and_preserve_exact_membership_after_reprints(): void
    {
        $first = $this->approved();
        $batch = $this->prepare();
        $url = '/api/enrollees/slip-printing/collection-list?batch_id='.$batch->id;
        $this->getJson($url)->assertStatus(409);
        $this->render($batch);
        $this->printing->complete($batch->id, $this->printer->id);
        $later = $this->approved();
        $reprint = $this->prepare(['identifier' => $first->enrollee_id]);
        $this->render($reprint);
        $this->printing->complete($reprint->id, $this->printer->id);
        $first->update(['status' => Enrollee::STATUS_SUSPENDED]);
        $this->travel(8)->days();
        $lists = app(EnrollmentSlipCollectionListService::class);
        $this->assertSame([$first->id], $lists->rows(['batch_id' => $batch->id], $this->printer->id)->modelKeys());
        $this->assertNull($later->fresh()->slip_first_generated_at);
        $this->actingAs($this->printer(), 'sanctum')->getJson($url)->assertNotFound();
        $this->actingAs($this->printer, 'sanctum');
        $scopes = Enrollee::getAllGlobalScopes();
        try {
            Enrollee::addGlobalScope('hide-collection-enrollee', fn ($q) => $q->where('enrollees.id', '<>', $first->id));
            $this->getJson($url)->assertForbidden();
            $this->getJson('/api/enrollees/slip-printing/collection-list?identifier='.$first->enrollee_id)->assertNotFound();
        } finally {
            Enrollee::setAllGlobalScopes($scopes);
        }
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson($url)->assertForbidden();
    }

    public function test_collection_form_uses_facility_location_and_escapes_enrollee_details(): void
    {
        $facility = Facility::factory()->create(['name' => 'PHC Collection Test']);
        $residence = Lga::create(['name' => 'Different Residence LGA', 'code' => 'TEST-'.uniqid(), 'zone' => 1, 'status' => 1]);
        $first = $this->approved([
            'facility_id' => $facility->id, 'lga_id' => $residence->id,
            'first_name' => 'First & Last', 'address' => '<script>unsafe</script>',
            'slip_first_generated_at' => now(),
        ]);
        $lists = app(EnrollmentSlipCollectionListService::class);
        $rows = $lists->rows(['facility_lga_id' => $facility->lga_id], $this->printer->id);
        $html = view('pdf.enrollment-slip-collection-list', [
            'groups' => $rows->groupBy('facility_id'), 'printedAt' => now('Africa/Lagos'), 'batchId' => null,
        ])->render();
        $this->assertStringContainsString('LGA: '.$facility->lga->name, $html);
        $this->assertStringNotContainsString('Different Residence LGA', $html);
        $this->assertStringContainsString('WARD: '.$facility->ward->name, $html);
        $this->assertStringContainsString('PHC Collection Test', $html);
        $this->assertStringContainsString('First &amp; Last', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringContainsString('DATE<br>COLLECTED', $html);
        $this->assertStringContainsString('>SIGN</th>', $html);
        $this->assertSame([$first->id], $rows->modelKeys());
    }

    public function test_collection_form_renders_a_printable_pdf_with_multiple_pages_and_facilities(): void
    {
        $facility = Facility::factory()->create(['name' => 'PHC 123 QUARTERS']);
        $secondFacility = Facility::factory()->create(['name' => 'SECOND COLLECTION FACILITY']);
        Enrollee::factory()->count(28)->create([
            'first_name' => 'Aisha', 'middle_name' => 'Muhammad', 'last_name' => 'Ibrahim',
            'address' => '12 Hospital Road, opposite the community primary school, Minna, Niger State',
            'phone' => '08012345678', 'nin' => '01234567890', 'facility_id' => $facility->id,
            'status' => 1, 'approval_date' => now(), 'slip_first_generated_at' => now(), 'slip_generation_count' => 1,
        ]);
        $this->approved(['facility_id' => $secondFacility->id, 'slip_first_generated_at' => now(), 'slip_generation_count' => 1]);
        $response = $this->get('/api/enrollees/slip-printing/collection-list?printing_status=generated')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Storage::disk('local')->put('form-c-preview.pdf', $response->getContent());
        $this->assertSame(29, Enrollee::where('slip_generation_count', 1)->count());
        $this->assertSame(0, EnrollmentSlipPrintBatch::where('user_id', $this->printer->id)->count());
    }

    private function approved(array $attributes = []): Enrollee
    {
        return Enrollee::factory()->create(array_merge(['status' => 1, 'approval_date' => now()], $attributes));
    }

    private function prepare(array $filters = []): EnrollmentSlipPrintBatch
    {
        return $this->printing->prepare($filters, $this->printer->id);
    }

    private function render(EnrollmentSlipPrintBatch $batch, int $part = 1): array
    {
        return $this->printing->part($batch->id, $this->printer->id, $part, fn ($enrollees) => 'PDF '.$enrollees->pluck('id')->implode(','));
    }

    private function printer(): User
    {
        $role = Role::create(['name' => 'slip-printer-'.uniqid(), 'label' => 'Slip printing test']);
        $role->permissions()->sync([Permission::firstOrCreate(['name' => 'enrollee.print-bulk-slip'], ['label' => 'Print slips'])->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        return $user;
    }
}
