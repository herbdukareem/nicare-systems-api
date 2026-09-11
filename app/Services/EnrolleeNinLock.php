<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EnrolleeNinLock
{
    public function run(int $enrolleeId, callable $callback): mixed
    {
        $lock = Cache::lock('enrollee-nin-mutation:'.$enrolleeId, 300);
        if (! $lock->get()) {
            throw new RuntimeException('A NIN update or verification is in progress for this enrollee. Try again later.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
