<?php

namespace App\Console\Commands;

use App\Services\EligibilityService;
use Illuminate\Console\Command;

class ActivateDueCoverageCommand extends Command
{
    protected $signature = 'coverage:activate-due';

    protected $description = 'Activate coverage periods whose waiting period has ended.';

    public function handle(EligibilityService $eligibility): int
    {
        $count = $eligibility->activateDueWaitingPeriods();
        $this->info("Activated {$count} due coverage period(s).");

        return self::SUCCESS;
    }
}
