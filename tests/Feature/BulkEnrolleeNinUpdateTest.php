<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EnrolleeNinLock;
use App\Services\NinVerificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class BulkEnrolleeNinUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_upload_updates_and_clears_unverified_nins_and_audits_changes(): void
    {
        $user = $this->editor();
        $first = Enrollee::factory()->create(['nin' => null]);
        $second = Enrollee::factory()->create([
            'nin' => '11111111111',
            'nin_verification_status' => Enrollee::NIN_VERIFICATION_FAILED,
            'nin_verified_at' => now(),
            'nin_verified_by' => $user->id,
            'nin_verification_provider' => 'Previous provider',
            'nin_verification_data' => ['old' => true],
            'nin_verification_meta' => ['message' => 'Failed'],
            'has_duplicate_nin' => true,
        ]);
        $duplicate = Enrollee::factory()->create(['nin' => '11111111111', 'has_duplicate_nin' => true]);
        $untouched = Enrollee::factory()->create(['nin' => '33333333333']);

        $this->actingAs($user, 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
            'file' => $this->csv("\xEF\xBB\xBFNICARE ID,NIN\r\n{$first->enrollee_id},01234567890\r\n{$second->enrollee_id},  \r\n"),
        ])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.cleared', 1)->assertJsonPath('data.skipped', 0);

        $this->assertSame('01234567890', $first->fresh()->nin);
        $this->assertSame(Enrollee::NIN_VERIFICATION_NOT_STARTED, $first->fresh()->nin_verification_status);
        $this->assertNull($second->fresh()->nin);
        $this->assertSame(Enrollee::NIN_VERIFICATION_NOT_PROVIDED, $second->fresh()->nin_verification_status);
        foreach (['nin_verified_at', 'nin_verified_by', 'nin_verification_provider', 'nin_verification_data', 'nin_verification_meta'] as $field) {
            $this->assertNull($second->fresh()->getAttribute($field));
        }
        $this->assertFalse($second->fresh()->has_duplicate_nin);
        $this->assertFalse($duplicate->fresh()->has_duplicate_nin);
        $this->assertSame('33333333333', $untouched->fresh()->nin);
        $audit = AuditTrail::where('auditable_id', $second->id)->where('action', 'bulk_nin_updated')->firstOrFail();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('11111111111', $audit->old_values['nin']);
        $this->assertNull($audit->new_values['nin']);
    }

    public function test_verified_nins_cannot_be_replaced_or_cleared(): void
    {
        $first = Enrollee::factory()->create(['nin' => '11111111111', 'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED, 'nin_verification_data' => ['verified_nin' => '11111111111']]);
        $second = Enrollee::factory()->create(['nin' => '22222222222', 'nin_verification_status' => Enrollee::NIN_VERIFICATION_VERIFIED]);
        $this->actingAs($this->editor(), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
            'file' => $this->csv("NICARE ID,NIN\n{$first->enrollee_id},99999999999\n{$second->enrollee_id},\n"),
        ])->assertOk()->assertJsonPath('data.skipped', 2)->assertJsonPath('data.updated', 0)->assertJsonPath('data.cleared', 0)
            ->assertJsonPath('data.rows.0.message', 'Verified NINs cannot be updated or cleared.');
        $this->assertSame('11111111111', $first->fresh()->nin);
        $this->assertSame(['verified_nin' => '11111111111'], $first->fresh()->nin_verification_data);
        $this->assertSame('22222222222', $second->fresh()->nin);
        $this->assertSame(0, AuditTrail::whereIn('auditable_id', [$first->id, $second->id])->where('action', 'bulk_nin_updated')->count());
    }

    public function test_invalid_missing_conflicting_and_repeated_rows_are_skipped(): void
    {
        $enrollees = Enrollee::factory()->count(6)->create(['nin' => null]);
        Enrollee::factory()->create(['nin' => '99999999999']);
        $csv = "Enrollee ID,NIN\n{$enrollees[0]->enrollee_id},123\n{$enrollees[1]->enrollee_id},99999999999\n{$enrollees[2]->enrollee_id}\n{$enrollees[3]->enrollee_id},11111111111\n{$enrollees[3]->enrollee_id},\nUNKNOWN,12345678901\n{$enrollees[4]->enrollee_id},12345678901\n{$enrollees[5]->enrollee_id},12345678901\n";
        $this->actingAs($this->editor(), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', ['file' => $this->csv($csv)])
            ->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.skipped', 7);
        foreach ([0, 1, 2, 3, 5] as $index) {
            $this->assertNull($enrollees[$index]->fresh()->nin);
        }
    }

    public function test_missing_nin_header_cannot_clear_records(): void
    {
        $enrollee = Enrollee::factory()->create(['nin' => '11111111111']);
        $this->actingAs($this->editor(), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
            'file' => $this->csv("NICARE ID\n{$enrollee->enrollee_id}\n"),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame('11111111111', $enrollee->fresh()->nin);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('excelFormats')]
    public function test_excel_preserves_text_nin_and_clears_explicitly_empty_cells(string $format, string $extension): void
    {
        $first = Enrollee::factory()->create(['nin' => null]);
        $second = Enrollee::factory()->create(['nin' => '22222222222']);
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([['NICARE ID', 'NIN'], [$first->enrollee_id, '01234567890'], [$second->enrollee_id, null]]);
        $file = tempnam(sys_get_temp_dir(), 'nin-test-');
        try {
            IOFactory::createWriter($workbook, $format)->save($file);
            $this->actingAs($this->editor(), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
                'file' => new UploadedFile($file, 'nins.'.$extension, null, null, true),
            ])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.cleared', 1);
            $this->assertSame('01234567890', $first->fresh()->nin);
            $this->assertNull($second->fresh()->nin);
        } finally {
            $workbook->disconnectWorksheets();
            @unlink($file);
        }
    }

    public function test_view_permission_does_not_allow_bulk_nin_updates(): void
    {
        $this->actingAs($this->editor('enrollees.view'), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
            'file' => $this->csv("NICARE ID,NIN\nUNKNOWN,\n"),
        ])->assertForbidden();
    }

    public static function excelFormats(): array
    {
        return [['Xlsx', 'xlsx'], ['Xls', 'xls']];
    }

    public function test_oversized_upload_is_rejected_before_any_rows_change(): void
    {
        $enrollee = Enrollee::factory()->create(['nin' => '11111111111']);
        $this->actingAs($this->editor(), 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
            'file' => $this->csv("NICARE ID,NIN\n{$enrollee->enrollee_id},\n".str_repeat("UNKNOWN,\n", 5000)),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame('11111111111', $enrollee->fresh()->nin);
    }

    public function test_upload_and_verification_share_the_same_mutation_lock(): void
    {
        $enrollee = Enrollee::factory()->create(['nin' => '11111111111']);
        $user = $this->editor();
        app(EnrolleeNinLock::class)->run($enrollee->id, function () use ($enrollee, $user): void {
            $this->actingAs($user, 'sanctum')->postJson('/api/enrollees/integrity/bulk-update-nin', [
                'file' => $this->csv("NICARE ID,NIN\n{$enrollee->enrollee_id},\n"),
            ])->assertOk()->assertJsonPath('data.skipped', 1);
            try {
                app(NinVerificationService::class)->verify($enrollee, $user);
                $this->fail('Verification must not run during an upload.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('in progress', $exception->getMessage());
            }
        });
        $this->assertSame('11111111111', $enrollee->fresh()->nin);
        $this->assertFalse(Cache::has('enrollee-nin-mutation:'.$enrollee->id));
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('nins.csv', $content);
    }

    private function editor(string $permission = 'enrollees.update'): User
    {
        $role = Role::create(['name' => 'bulk-nin-'.uniqid(), 'label' => 'Bulk NIN test']);
        $role->permissions()->sync([Permission::firstOrCreate(['name' => $permission], ['label' => $permission])->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
