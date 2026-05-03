<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request as McpRequest;
use Visualbuilder\YoutrackCli\Mcp\Tools\Assign;
use Visualbuilder\YoutrackCli\Mcp\Tools\GetIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReady;
use Visualbuilder\YoutrackCli\Mcp\YoutrackServer;

// `decodeMcpResponse()` lives in tests/Pest.php — shared across every Mcp test.

it('registers all twenty MCP tools on the YoutrackServer', function (): void {
    $reflection = new ReflectionClass(YoutrackServer::class);
    $tools = $reflection->getDefaultProperties()['tools'] ?? [];

    expect($tools)->toBeArray()->toHaveCount(20);

    foreach ($tools as $tool) {
        expect(class_exists($tool))->toBeTrue("Tool class {$tool} must exist")
            ->and(is_subclass_of($tool, \Laravel\Mcp\Server\Tool::class))
            ->toBeTrue("Tool {$tool} must extend Laravel\\Mcp\\Server\\Tool");
    }
});

it('ListReady (read tool) returns normalised issues for the configured ready state', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);
    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'summary' => 'Hi',
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Ready for Dev']],
                ['name' => 'Priority', 'value' => ['name' => 'P3']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
            ],
        ],
    ])]);

    $response = (new ListReady)->handle(new McpRequest(['project' => 'NB']));

    $payload = decodeMcpResponse($response);

    expect($response->isError())->toBeFalse()
        ->and($payload['count'])->toBe(1)
        ->and($payload['state'])->toBe('Ready for Dev')
        ->and($payload['issues'][0]['id'])->toBe('NB-1');
});

it('GetIssue (read tool) emits Response::error when issue_id is missing', function (): void {
    $response = (new GetIssue)->handle(new McpRequest([]));

    expect($response->isError())->toBeTrue();
});

it('Assign (write tool) honours the instance param and routes to a non-default workspace', function (): void {
    config([
        'youtrack.connections.support' => [
            'base_url' => 'https://support.youtrack.cloud',
            'token' => 'perm:support-token',
            'default_project' => 'SUPP',
        ],
    ]);

    Http::fake(['*' => Http::response(['id' => '3-1', 'idReadable' => 'SUPP-1'])]);

    $response = (new Assign)->handle(new McpRequest([
        'issue_id' => 'SUPP-1',
        'assignee' => 'lee',
        'instance' => 'support',
    ]));

    expect($response->isError())->toBeFalse()
        ->and(decodeMcpResponse($response))->toMatchArray([
            'success' => true,
            'assignee' => 'lee',
        ]);

    Http::assertSent(static fn ($request): bool =>
        // Routed to the *support* base URL — not the default `example.youtrack.cloud`.
        str_starts_with($request->url(), 'https://support.youtrack.cloud/api/issues/SUPP-1')
    );
});

it('lists every artisan command surface as an MCP tool', function (): void {
    $reflection = new ReflectionClass(YoutrackServer::class);
    $tools = $reflection->getDefaultProperties()['tools'] ?? [];

    $expected = [
        // Reads
        'ListReady', 'ListBlocked', 'ListApproved', 'ListReadyForStaging', 'ListReadyForProduction',
        'GetIssue', 'Query', 'Search', 'BulkSearchFingerprints', 'CheckProject',
        // Writes
        'CreateIssue', 'UpdateIssue', 'AddComment', 'SetField', 'UpdateState',
        'Resolve', 'Reopen', 'Assign', 'Tag', 'Link',
    ];

    $actual = array_map(static fn (string $fqcn): string => class_basename($fqcn), $tools);

    foreach ($expected as $name) {
        expect($actual)->toContain($name);
    }
});
