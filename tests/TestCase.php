<?php

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $testingSchemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$testingSchemaReady) {
            Artisan::call('migrate', ['--force' => true]);
            static::$testingSchemaReady = true;
        }
    }
}
