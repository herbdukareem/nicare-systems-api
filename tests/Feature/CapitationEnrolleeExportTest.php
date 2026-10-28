<?php

namespace Tests\Feature;

use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\CapitationDetailEnrollee;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CapitationEnrolleeExportTest extends TestCase
{
    use DatabaseTransactions;

    private Capitation $capitation;
    private CapitationDetail $detail;
    private Facility $facility;
    private FundingType $fundingType;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $permission = Permission::firstOrCreate(['name' => 'capitation.enrollee-list.view']);
        $role = Role::firstOrCreate(['name' => 'snapshot-export-test']);
        $role->permissions()->sync([$permission->id]);
        $user->roles()->sync([$role->id]);
        $user->forceFill(['current_role_id' => $role->id])->save();
        $this->actingAs($user, 'sanctum');

        $this->capitation = Capitation::create([
            'name' => 'October 2026 Capitation',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'capitation_month' => 10, 'capitated_month' => 10, 'year' => 2026,
            'status' => false, 'user_id' => $user->id, 'created_by' => $user->id,
        ]);
        $this->facility = Facility::factory()->create();
        $this->fundingType = FundingType::create(['name' => 'Export funding', 'capitation_rate' => 570, 'status' => 1]);
        $this->detail = CapitationDetail::create([
            'capitation_id' => $this->capitation->id,
            'facility_id' => $this->facility->id, 'funding_type_id' => $this->fundingType->id,
            'capitated_month' => 10, 'total_enrollees' => 5000, 'total_enrolled' => 5000,
            'capitation_rate' => 570, 'rate' => 570, 'total_amount' => 2850000, 'amount' => 2850000,
            'status' => 1,
        ]);
    }

    public function test_large_export_has_one_header_and_no_missing_or_duplicate_rows_across_batches_and_retries(): void
    {
        $rows = [];
        for ($i = 0; $i < 4501; $i++) {
            $rows[] = $this->snapshotAttributes(['enrollee_number' => sprintf('%08d', $i)]);
        }
        foreach (array_chunk($rows, 500) as $batch) {
            CapitationDetailEnrollee::insert($batch);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.row_count', 2000)
            ->assertJsonPath('data.has_more', true)->json('data');

        // Snapshot repair must not run on the download path, even when a detail's
        // expected enrollee count is greater than its stored snapshot count.
        $snapshotQueries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'capitation_detail_enrollees'));
        $this->assertCount(2, $snapshotQueries);
        $this->assertStringContainsString('limit 2001', $snapshotQueries->last());
        $this->assertStringNotContainsString('metadata', $snapshotQueries->last());
        $this->assertStringNotContainsString('order by `facility_name`', $snapshotQueries->last());
        DB::disableQueryLog();

        // New snapshots arriving mid-export must belong to the next export.
        CapitationDetailEnrollee::create($this->snapshotAttributes(['enrollee_number' => 'LATE']));
        $retry = $this->getJson($this->url(['snapshot_max_id' => $first['snapshot_max_id']]))
            ->assertOk()->json('data');
        $this->assertSame($first['csv'], $retry['csv']);
        $this->assertSame($first['next_cursor'], $retry['next_cursor']);

        $second = $this->getJson($this->url([
            'after_id' => $first['next_cursor'], 'snapshot_max_id' => $first['snapshot_max_id'],
        ]))->assertOk()->assertJsonPath('data.row_count', 2000)->assertJsonPath('data.has_more', true)->json('data');
        $last = $this->getJson($this->url([
            'after_id' => $second['next_cursor'], 'snapshot_max_id' => $first['snapshot_max_id'],
        ]))->assertOk()->assertJsonPath('data.row_count', 501)->assertJsonPath('data.has_more', false)->json('data');

        $csv = $first['csv'] . $second['csv'] . $last['csv'];
        $this->assertSame(1, substr_count($csv, "\xEF\xBB\xBF"));
        $this->assertSame(1, substr_count($csv, '"Capitation Period"'));
        $parsed = $this->parseCsv($csv);
        $this->assertCount(4502, $parsed);
        $this->assertCount(18, $parsed[0]);
        $numbers = array_column(array_slice($parsed, 1), 4);
        $this->assertSame(array_column($rows, 'enrollee_number'), $numbers);
        $this->assertCount(4501, array_unique($numbers));
        $this->assertSame("Stored, \"Historical\"\nName", $parsed[1][6]);
        $this->assertSame('00123456789', $parsed[1][7]);
        $this->assertSame('08001234567', $parsed[1][8]);
        $this->assertSame('1990-02-03', $parsed[1][10]);
        $this->assertSame('', $parsed[1][14]);
        $this->assertSame('INCLUDE', $parsed[1][16]);
        $this->assertSame('2026-10-20 12:34:56', $parsed[1][17]);
    }

    public function test_every_batch_keeps_period_funding_facility_and_search_filters(): void
    {
        $otherFacility = Facility::factory()->create();
        $otherFunding = FundingType::create(['name' => 'Other funding', 'capitation_rate' => 570, 'status' => 1]);
        $otherPeriod = $this->capitation->replicate();
        $otherPeriod->name = 'Another period';
        $otherPeriod->save();
        foreach ([
            ['enrollee_number' => 'MATCH-1'],
            ['enrollee_number' => 'MATCH-2'],
            ['enrollee_number' => 'EXCLUDE-1', 'capitation_id' => $otherPeriod->id],
            ['enrollee_number' => 'EXCLUDE-2', 'facility_id' => $otherFacility->id],
            ['enrollee_number' => 'EXCLUDE-3', 'funding_type_id' => $otherFunding->id],
            ['enrollee_number' => 'EXCLUDE-4', 'full_name' => 'Does not match'],
        ] as $row) {
            CapitationDetailEnrollee::create($this->snapshotAttributes($row));
        }
        $params = [
            'funding_type_id' => $this->fundingType->id, 'facility_id' => $this->facility->id,
            'search' => 'Stored,', 'chunk_size' => 1,
        ];
        $first = $this->getJson($this->url($params))->assertOk()
            ->assertJsonPath('data.row_count', 1)->assertJsonPath('data.has_more', true)->json('data');
        $last = $this->getJson($this->url($params + [
            'after_id' => $first['next_cursor'], 'snapshot_max_id' => $first['snapshot_max_id'],
        ]))->assertOk()->assertJsonPath('data.row_count', 1)->assertJsonPath('data.has_more', false)->json('data');
        $parsed = $this->parseCsv($first['csv'] . $last['csv']);
        $this->assertSame(['MATCH-1', 'MATCH-2'], array_column(array_slice($parsed, 1), 4));
    }

    public function test_empty_export_returns_only_headings_and_does_not_attempt_backfill(): void
    {
        $data = $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.row_count', 0)->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.next_cursor', 0)->json('data');
        $this->assertCount(1, $this->parseCsv($data['csv']));
    }

    public function test_export_validates_batch_limits_and_requires_a_cutoff_to_continue(): void
    {
        $this->getJson($this->url(['chunk_size' => 5001]))->assertUnprocessable()->assertJsonValidationErrors('chunk_size');
        $this->getJson($this->url(['after_id' => 1]))->assertUnprocessable()->assertJsonValidationErrors('snapshot_max_id');
        $this->getJson($this->url(['after_id' => -1]))->assertUnprocessable()->assertJsonValidationErrors('after_id');
    }

    public function test_export_requires_enrollee_list_permission(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url())->assertForbidden()
            ->assertJsonPath('required_permissions.0', 'capitation.enrollee-list.view');
    }

    private function url(array $params = []): string
    {
        return "/api/capitation/periods/{$this->capitation->id}/export-enrollee-list/chunk?" . http_build_query($params);
    }

    private function snapshotAttributes(array $overrides = []): array
    {
        return array_merge([
            'capitation_id' => $this->capitation->id, 'capitation_detail_id' => $this->detail->id,
            'facility_id' => $this->facility->id, 'funding_type_id' => $this->fundingType->id,
            'enrollee_number' => '00012345', 'legacy_id' => '0007',
            'full_name' => "Stored, \"Historical\"\nName", 'nin' => '00123456789', 'phone' => '08001234567',
            'gender' => 'Female', 'date_of_birth' => '1990-02-03',
            'facility_name' => 'Historical Clinic', 'facility_code' => '001', 'funding_type_name' => 'Stored Funding',
            'lga_name' => 'Stored LGA', 'ward_name' => 'Stored Ward',
            'coverage_start_date' => '2026-01-01', 'coverage_end_date' => null,
            'capitation_start_date' => '2026-01-01', 'duplicate_nin_policy' => 'include',
            'captured_at' => '2026-10-20 12:34:56',
        ], $overrides);
    }

    private function parseCsv(string $csv): array
    {
        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, $csv);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
