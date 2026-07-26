<?php

namespace App\Console\Commands;

use App\Services\EnrolleeDuplicateNinService;
use Illuminate\Console\Command;

class FlagDuplicateNinsCommand extends Command
{
    protected $signature = 'enrollees:flag-duplicate-nins';

    protected $description = 'Mark enrollees whose NIN appears more than once in the system.';

    public function handle(EnrolleeDuplicateNinService $service): int
    {
        $this->info('Refreshing duplicate NIN flags...');

        $service->refreshAll();

        $this->info('Duplicate NIN flags refreshed successfully.');

        return self::SUCCESS;
    }
}
