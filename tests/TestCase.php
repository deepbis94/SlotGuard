<?php

namespace Tests;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // `php artisan test` boots once under Compose's pgsql env. Force the
        // PHPUnit application onto SQLite before RefreshDatabase runs.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.sqlite.foreign_key_constraints', true);
        $app['db']->purge();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-14 08:00:00');
        CarbonImmutable::setTestNow('2026-09-14 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
