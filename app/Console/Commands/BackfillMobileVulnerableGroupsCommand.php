<?php

namespace App\Console\Commands;

use App\Models\Enrollee;
use App\Services\VulnerableGroupAssignmentService;
use Illuminate\Console\Command;

class BackfillMobileVulnerableGroupsCommand extends Command
{
    protected $signature = 'mobile:backfill-vulnerable-groups {--dry-run : Preview the mobile enrollee rows that would be updated}';

    protected $description = 'Backfill vulnerable_group_id for mobile enrollees using the deterministic vulnerable-group rules and Others fallback.';

    public function handle(VulnerableGroupAssignmentService $assignmentService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stats = [
            'scanned' => 0,
            'eligible' => 0,
            'updated' => 0,
            'unchanged' => 0,
        ];

        Enrollee::query()
            ->with(['insuranceProgramme:id,name,code', 'enrolleeCategory:id,insurance_programme_id,name,code', 'premiumPlan:id,insurance_programme_id,name,code'])
            ->whereNotNull('mobile_enrollment_record_id')
            ->whereNull('vulnerable_group_id')
            ->orderBy('id')
            ->chunkById(200, function ($enrollees) use ($assignmentService, $dryRun, &$stats): void {
                foreach ($enrollees as $enrollee) {
                    $stats['scanned']++;
                    $resolvedId = $assignmentService->resolveForEnrollee($enrollee);

                    if ($resolvedId === null) {
                        $stats['unchanged']++;
                        continue;
                    }

                    $stats['eligible']++;

                    if ($dryRun) {
                        $this->line(sprintf(
                            'Would assign vulnerable_group_id=%d to enrollee #%d (%s).',
                            $resolvedId,
                            $enrollee->id,
                            $enrollee->enrollee_id ?: $enrollee->full_name ?: trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->last_name ?? ''))
                        ));
                        continue;
                    }

                    $assignmentService->syncForEnrollee($enrollee);
                    $stats['updated']++;
                }
            });

        $this->table(
            ['Metric', 'Value'],
            [
                ['Scanned mobile rows', (string) $stats['scanned']],
                ['Eligible vulnerable rows', (string) $stats['eligible']],
                ['Updated rows', (string) $stats['updated']],
                ['Left unchanged', (string) $stats['unchanged']],
                ['Mode', $dryRun ? 'dry-run' : 'write'],
            ]
        );

        return self::SUCCESS;
    }
}
