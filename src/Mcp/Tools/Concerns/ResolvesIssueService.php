<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools\Concerns;

use Laravel\Mcp\Request;
use Visualbuilder\YoutrackCli\Services\IssueService;

/**
 * Every YouTrack MCP tool accepts an optional `instance` param naming a
 * connection from `config('youtrack.connections.*')`. This trait lets each
 * tool resolve the right service in one call without re-implementing the
 * dispatch logic.
 */
trait ResolvesIssueService
{
    protected function service(Request $request): IssueService
    {
        $instance = (string) ($request->get('instance') ?? '');

        $service = app(IssueService::class);

        return $instance !== '' ? $service->on($instance) : $service;
    }
}
