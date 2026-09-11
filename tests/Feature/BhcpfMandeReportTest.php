<?php

namespace Tests\Feature;

use App\Exports\BhcpfMandeReportExport;
use App\Models\Enrollee;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class BhcpfMandeReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_download_matches_legacy_columns_styling_and_data_selection(): void
    {
        $funding = FundingType::create(['name' => 'Basic Healthcare Provision Fund', 'status' => 1]);
        $counterpart = FundingType::create(['name' => 'BHCPF-CF', 'status' => 1]);
        $older = Enrollee::factory()->create(['funding_type_id' => $funding->id, 'enrollment_date' => '2026-09-01']);
        $enrollee = Enrollee::factory()->create([
            'funding_type_id' => $funding->id, 'legacy_enrollee_id' => '001234',
            'first_name' => 'First & Name', 'middle_name' => 'Middle', 'last_name' => 'Last',
            'nin' => '01234567890', 'phone' => '08012345678', 'sex' => 2,
            'date_of_birth' => '1995-03-04', 'enrollment_date' => '2026-09-11',
            'address' => 'Home', 'village' => 'Village', 'disability' => 'None',
            'email' => 'not-exported@example.test', 'occupation' => 'Teacher/Lecturer',
        ]);
        $pending = Enrollee::factory()->create(['funding_type_id' => $funding->id, 'status' => Enrollee::STATUS_PENDING]);
        $other = Enrollee::factory()->create(['funding_type_id' => $counterpart->id]);
        $formalProgramme = InsuranceProgramme::firstOrCreate(['code' => 'formal_sector'], ['name' => 'Formal Sector', 'status' => 1]);
        $formal = Enrollee::factory()->create(['funding_type_id' => $funding->id, 'insurance_programme_id' => $formalProgramme->id]);

        $response = $this->actingAs($this->exporter(), 'sanctum')->get('/api/reports/bhcpf-mande?from_date=2026-09-01&to_date=2026-09-11');
        $response->assertOk()->assertDownload('BHCPF_Enrollees_'.now()->format('Y-m-d').'.xls');
        $html = $this->downloadContents($response);
        preg_match_all('/<th>(.*?)<\/th>/', $html, $headings);
        $this->assertSame([
            'SN', 'STATE', 'INSURANCE ID', 'PROGRAM', 'FIRST NAME', 'MIDDLE NAME',
            'LAST NAME', 'DOB', 'GENDER', 'ADDRESS', 'LGA', 'WARD', 'COMMUNITY',
            'OCCUPATION', 'NIN', 'PHONE', 'EMAIL', 'MARITAL STATUS', 'EDUCATIONAL STATUS',
            'SPECIAL NEED', 'ENROLLED AT', 'FACILITY NAME', 'FACILITY LGA', 'FACILITY WARD', 'DATE OF ENROLLMENT',
        ], $headings[1]);
        $this->assertStringContainsString('background-color: #CCCCCC', $html);
        $this->assertStringContainsString('border: 2px solid #000000', $html);
        $this->assertStringContainsString('mso-number-format:"\\@"', $html);
        $this->assertStringContainsString('<x:Name>BHCPF Enrollees</x:Name>', $html);
        $this->assertStringContainsString('<td class="text">01234567890</td>', $html);
        $this->assertStringContainsString('<td class="text">08012345678</td>', $html);
        $this->assertStringContainsString('First &amp; Name', $html);

        preg_match_all('/<tr>(<td.*?<\/td>)<\/tr>/s', $html, $rows);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $rows[1][0], $cells);
        $this->assertSame([
            '2', 'Niger', '001234', 'BHCPF', 'First & Name', 'Middle', 'Last',
            '04/03/1995', 'FEMALE', 'Home', $enrollee->lga->name, $enrollee->ward->name,
            'Village', '', '01234567890', '08012345678', '', '', '', 'None',
            $enrollee->facility->name, $enrollee->facility->name,
            $enrollee->facility->lga?->name ?? '', $enrollee->facility->ward?->name ?? '', '11/09/2026',
        ], array_map(fn (string $value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $cells[1]));
        $this->assertCount(2, $rows[1]);
        $this->assertStringContainsString($older->enrollee_id, $html);
        foreach ([$pending, $other, $formal] as $excluded) {
            $this->assertStringNotContainsString($excluded->enrollee_id, $html);
        }
    }

    public function test_end_date_only_and_empty_report_keep_template_headers(): void
    {
        $response = $this->actingAs($this->exporter(), 'sanctum')->get('/api/reports/bhcpf-mande?to_date=1900-01-01');
        $response->assertOk();
        $html = $this->downloadContents($response);
        $this->assertSame(25, substr_count($html, '<th>'));
        $this->assertSame(0, substr_count($html, '<td'));
    }

    public function test_export_requires_permission_and_valid_dates(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/reports/bhcpf-mande')->assertForbidden();
        $this->actingAs($this->exporter(), 'sanctum')->getJson('/api/reports/bhcpf-mande?from_date=2026-09-11&to_date=2026-09-01')
            ->assertUnprocessable()->assertJsonValidationErrors('to_date');
    }

    public function test_large_download_preserves_date_and_id_order_across_batches_including_null_dates(): void
    {
        $funding = FundingType::create(['name' => 'BHCPF', 'status' => 1]);
        $base = Enrollee::factory()->make(['funding_type_id' => $funding->id, 'nin' => null])->getAttributes();
        $expected = [];
        foreach (array_chunk(range(1, 4005), 500) as $numbers) {
            $rows = [];
            foreach ($numbers as $number) {
                // Each date group crosses a batch boundary; IDs alone have a different order.
                $date = $number % 2 === 0 ? '2026-09-11 23:59:59' : null;
                $identifier = 'REPORT'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
                $rows[] = [...$base, 'enrollee_id' => $identifier, 'enrollment_date' => $date];
                $expected[$number] = $identifier;
            }
            Enrollee::query()->insert($rows);
        }
        $expectedOrder = [
            ...array_reverse(array_filter($expected, fn ($number) => $number % 2 === 0, ARRAY_FILTER_USE_KEY)),
            ...array_reverse(array_filter($expected, fn ($number) => $number % 2 !== 0, ARRAY_FILTER_USE_KEY)),
        ];

        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            if (str_contains($event->sql, 'select') && str_contains($event->sql, 'from `enrollees`')) {
                $queries[] = $event->sql;
            }
        });
        $response = $this->actingAs($this->exporter(), 'sanctum')->get('/api/reports/bhcpf-mande');
        $response->assertOk();
        $html = $this->downloadContents($response);
        preg_match_all('/<td class="text">(REPORT\d+)<\/td>/', $html, $identifiers);
        $this->assertSame($expectedOrder, $identifiers[1]);
        $this->assertStringContainsString('<tr><td>4005</td>', $html);
        $this->assertStringContainsString('<tr><td>1</td>', $html);
        $this->assertStringEndsWith('</table></body></html>', $html);
        // One ordered ID list and three data batches, without OFFSET scans or relationship queries.
        $this->assertCount(4, $queries);
        $this->assertStringNotContainsString(' offset ', strtolower(implode(' ', $queries)));
    }

    public function test_generation_failure_does_not_write_a_partial_download_to_the_response(): void
    {
        $funding = FundingType::create(['name' => 'BHCPF', 'status' => 1]);
        Enrollee::factory()->count(2)->create(['funding_type_id' => $funding->id]);
        $export = new class extends BhcpfMandeReportExport
        {
            private int $mapped = 0;

            public function map(\stdClass $enrollee, int $serial): array
            {
                if (++$this->mapped === 2) {
                    throw new \RuntimeException('Simulated report generation failure.');
                }

                return parent::map($enrollee, $serial);
            }
        };

        ob_start();
        try {
            $export->download();
            $this->fail('An incomplete export must not return a download response.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated report generation failure.', $exception->getMessage());
            $this->assertSame('', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    public function test_download_preserves_facility_soft_delete_scope(): void
    {
        $funding = FundingType::create(['name' => 'BHCPF', 'status' => 1]);
        $enrollee = Enrollee::factory()->create(['funding_type_id' => $funding->id]);
        $enrollee->facility->update(['name' => 'REMOVED FACILITY']);
        $enrollee->facility->delete();

        $response = $this->actingAs($this->exporter(), 'sanctum')->get('/api/reports/bhcpf-mande');
        $response->assertOk();
        $html = $this->downloadContents($response);
        $this->assertStringContainsString($enrollee->enrollee_id, $html);
        $this->assertStringNotContainsString('REMOVED FACILITY', $html);
    }

    private function downloadContents(TestResponse $response): string
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        ob_start();
        try {
            $response->baseResponse->sendContent();
            $contents = ob_get_contents();
            $this->assertSame(strlen($contents), (int) $response->headers->get('Content-Length'));

            return $contents;
        } finally {
            ob_end_clean();
        }
    }

    private function exporter(): User
    {
        $role = Role::create(['name' => 'mande-'.uniqid(), 'label' => 'M&E report test']);
        $role->permissions()->sync([Permission::firstOrCreate(['name' => 'enrollees.export'], ['label' => 'Export enrollees'])->id]);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
