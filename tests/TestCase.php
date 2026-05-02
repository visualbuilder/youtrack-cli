<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Visualbuilder\YoutrackCli\YoutrackCliServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [YoutrackCliServiceProvider::class];
    }

    /**
     * Inject deterministic credentials so the YouTrack service is "configured"
     * in tests without ever needing a real token. `Http::fake()` captures every
     * outbound request before it leaves the machine.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('youtrack.base_url', 'https://example.youtrack.cloud');
        $app['config']->set('youtrack.token', 'perm:test-token');
        $app['config']->set('youtrack.default_project', 'NB');
    }
}
