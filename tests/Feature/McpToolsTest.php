<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request as McpRequest;
use Visualbuilder\YoutrackCli\Mcp\Tools\AddComment;
use Visualbuilder\YoutrackCli\Mcp\Tools\BulkSearchFingerprints;
use Visualbuilder\YoutrackCli\Mcp\Tools\CheckProject;
use Visualbuilder\YoutrackCli\Mcp\Tools\CreateIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\Link;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListApproved;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListBlocked;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReadyForProduction;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReadyForStaging;
use Visualbuilder\YoutrackCli\Mcp\Tools\Query;
use Visualbuilder\YoutrackCli\Mcp\Tools\Reopen;
use Visualbuilder\YoutrackCli\Mcp\Tools\Resolve;
use Visualbuilder\YoutrackCli\Mcp\Tools\Search;
use Visualbuilder\YoutrackCli\Mcp\Tools\SetField;
use Visualbuilder\YoutrackCli\Mcp\Tools\Tag;
use Visualbuilder\YoutrackCli\Mcp\Tools\UpdateIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\UpdateState;

/**
 * Smoke tests covering every MCP tool's `handle()` path with a faked HTTP
 * client. The shared decode helper lives in tests/Feature/McpServerTest.php
 * (autoloaded via Pest), so this file just exercises tools.
 *
 * Three reads (ListReady / GetIssue / Assign) are covered in McpServerTest.
 * This file fills the remaining 17 tools.
 */

/**
 * Faked YouTrack issue payload — the bare minimum normalizeIssue() needs to
 * produce a sensible record.
 *
 * @return array<string, mixed>
 */
function fakeIssue(string $id = 'NB-1'): array
{
    return [
        'id' => '3-1',
        'idReadable' => $id,
        'summary' => 'Subject',
        'description' => 'Body',
        'created' => 1_700_000_000_000,
        'updated' => 1_700_000_000_000,
        'customFields' => [
            ['name' => 'Status', 'value' => ['name' => 'Open']],
            ['name' => 'Priority', 'value' => ['name' => 'P3']],
            ['name' => 'Type', 'value' => ['name' => 'Bug']],
        ],
    ];
}

it('ListBlocked returns the configured Plan-Review state', function (): void {
    config(['youtrack.states.blocked' => 'Plan Review']);
    Http::fake(['*/issues*' => Http::response([fakeIssue('NB-2')])]);

    $response = (new ListBlocked)->handle(new McpRequest(['project' => 'NB']));
    $payload = decodeMcpResponse($response);

    expect($response->isError())->toBeFalse()
        ->and($payload)->toMatchArray([
            'count' => 1,
            'project' => 'NB',
            'state' => 'Plan Review',
        ]);
});

it('ListApproved returns the Developer-Approved state', function (): void {
    config(['youtrack.states.developer_approved' => 'Developer Approved']);
    Http::fake(['*/issues*' => Http::response([])]);

    $payload = decodeMcpResponse((new ListApproved)->handle(new McpRequest(['project' => 'NB'])));

    expect($payload['state'])->toBe('Developer Approved');
});

it('ListReadyForStaging returns the Ready-for-Staging state', function (): void {
    config(['youtrack.states.ready_for_staging' => 'Ready for Staging']);
    Http::fake(['*/issues*' => Http::response([])]);

    $payload = decodeMcpResponse((new ListReadyForStaging)->handle(new McpRequest(['project' => 'NB'])));

    expect($payload['state'])->toBe('Ready for Staging');
});

it('ListReadyForProduction returns the Ready-for-Production state', function (): void {
    config(['youtrack.states.ready_for_production' => 'Ready for Production']);
    Http::fake(['*/issues*' => Http::response([])]);

    $payload = decodeMcpResponse((new ListReadyForProduction)->handle(new McpRequest(['project' => 'NB'])));

    expect($payload['state'])->toBe('Ready for Production');
});

it('Query passes raw YQL through and respects per_page pagination', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    $payload = decodeMcpResponse((new Query)->handle(new McpRequest([
        'query' => 'assignee: me',
        'project' => 'NB',
        'per_page' => 25,
    ])));

    expect($payload)->toMatchArray([
        'query' => 'assignee: me',
        'page' => 1,
        'per_page' => 25,
    ]);

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'project: NB assignee: me')
            && str_contains($url, '$top=25');
    });
});

it('Query rejects requests with no YQL string', function (): void {
    expect((new Query)->handle(new McpRequest([]))->isError())->toBeTrue();
});

it('Search hits both summary and description', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    (new Search)->handle(new McpRequest(['query' => 'login', 'project' => 'NB']));

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'summary: {login}')
            && str_contains($url, 'description: {login}');
    });
});

it('BulkSearchFingerprints maps results back to the supplied fingerprints', function (): void {
    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-7',
            'description' => 'panic [error-fp:abc]',
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Open']],
                ['name' => 'Priority', 'value' => ['name' => 'P2']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
                ['name' => 'Error Count', '$type' => 'SimpleIssueCustomField', 'value' => 12],
            ],
        ],
    ])]);

    $payload = decodeMcpResponse((new BulkSearchFingerprints)->handle(new McpRequest([
        'fingerprints' => ['abc', 'def'],
        'project' => 'NB',
    ])));

    expect($payload)->toMatchArray(['count' => 1, 'project' => 'NB'])
        ->and($payload['results']['abc'])->toMatchArray([
            'issue_id' => 'NB-7',
            'state' => 'Open',
            'error_count' => 12,
        ])
        ->and($payload['results']['def'])->toBeNull();
});

it('BulkSearchFingerprints rejects non-array fingerprints input', function (): void {
    expect((new BulkSearchFingerprints)->handle(new McpRequest(['fingerprints' => 'not-an-array']))
        ->isError())->toBeTrue();
});

it('CheckProject reads admin/projects/{id} and tiers the configured fields', function (): void {
    Http::fake(['*/admin/projects/NB*' => Http::response([
        'shortName' => 'NB',
        'customFields' => [
            ['field' => ['name' => 'Status']],
            ['field' => ['name' => 'Priority']],
            ['field' => ['name' => 'Type']],
            ['field' => ['name' => 'PR URL']],
            ['field' => ['name' => 'Org-Specific']],
        ],
    ])]);

    $payload = decodeMcpResponse((new CheckProject)->handle(new McpRequest(['project' => 'NB'])));

    expect($payload)->toMatchArray([
        'project' => 'NB',
        'ready' => true,
    ])->and($payload['tier_2']['configured'])->toBe(['PR URL'])
        ->and($payload['extra_fields'])->toBe(['Org-Specific']);
});

it('CreateIssue forwards the structured request to YouTrack', function (): void {
    Http::fake(['*/issues*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-99'])]);

    $payload = decodeMcpResponse((new CreateIssue)->handle(new McpRequest([
        'project' => 'NB',
        'summary' => 'Halp',
        'description' => 'broken',
        'priority' => 'P2',
    ])));

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-99',
    ]);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $body['summary'] === 'Halp'
            && collect($body['customFields'])->firstWhere('name', 'Priority')['value']['name'] === 'P2';
    });
});

it('CreateIssue rejects calls missing project or summary', function (): void {
    expect((new CreateIssue)->handle(new McpRequest(['project' => 'NB']))
        ->isError())->toBeTrue();
});

it('CreateIssue falls back to configured priority/type defaults when omitted', function (): void {
    config([
        'youtrack.priorities.default' => 'Major',
        'youtrack.types.default' => 'Story',
    ]);

    Http::fake(['*/issues*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-50'])]);

    (new CreateIssue)->handle(new McpRequest(['project' => 'NB', 'summary' => 'q']));

    Http::assertSent(static function ($request): bool {
        $custom = collect($request->data()['customFields']);

        return $custom->firstWhere('name', 'Type')['value']['name'] === 'Story'
            && $custom->firstWhere('name', 'Priority')['value']['name'] === 'Major';
    });
});

it('UpdateIssue patches summary and reports the changed field set', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $payload = decodeMcpResponse((new UpdateIssue)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'summary' => 'New title',
    ])));

    expect($payload)->toMatchArray(['success' => true, 'updated' => ['summary']]);
});

it('UpdateIssue requires at least one of summary or description', function (): void {
    expect((new UpdateIssue)->handle(new McpRequest(['issue_id' => 'NB-1']))
        ->isError())->toBeTrue();
});

it('AddComment posts the comment text and returns success metadata', function (): void {
    Http::fake(['*/issues/NB-1/comments*' => Http::response([
        'id' => 'c-1',
        'text' => 'hi',
    ])]);

    $payload = decodeMcpResponse((new AddComment)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'comment' => 'hi',
    ])));

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
    ]);
});

it('SetField coerces numeric strings to int before sending', function (): void {
    Http::fake([
        '*/issues/NB-1*' => Http::sequence()
            ->push([
                'id' => '3-1',
                'idReadable' => 'NB-1',
                'customFields' => [
                    ['name' => 'Error Count', '$type' => 'SimpleIssueCustomField', 'value' => 0],
                ],
            ])
            ->push(['id' => '3-1', 'idReadable' => 'NB-1']),
    ]);

    (new SetField)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'field' => 'Error Count',
        'value' => '42',
    ]));

    Http::assertSent(static function ($request): bool {
        if ($request->method() !== 'POST') {
            return false;
        }
        $body = $request->data();

        return ($body['customFields'][0]['name'] ?? null) === 'Error Count'
            && ($body['customFields'][0]['value'] ?? null) === 42;
    });
});

it('UpdateState posts the StateIssueCustomField in a single round trip', function (): void {
    Http::fake(['*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $payload = decodeMcpResponse((new UpdateState)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'state' => 'Code Review',
    ])));

    expect($payload)->toMatchArray([
        'success' => true,
        'new_state' => 'Code Review',
    ]);

    Http::assertSentCount(1);
});

it('Resolve sets Status and Resolution atomically', function (): void {
    config(['youtrack.states.done' => 'Done']);
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $payload = decodeMcpResponse((new Resolve)->handle(new McpRequest(['issue_id' => 'NB-1'])));

    expect($payload)->toMatchArray([
        'success' => true,
        'state' => 'Done',
        'resolution' => 'Fixed',
    ]);
});

it('Resolve honours the as / state overrides', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    (new Resolve)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'as' => 'Duplicate',
        'state' => 'Closed',
    ]));

    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Status']['value']['name'] === 'Closed'
            && $byName['Resolution']['value']['name'] === 'Duplicate';
    });
});

it('Reopen clears Resolution and resets the Status', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $payload = decodeMcpResponse((new Reopen)->handle(new McpRequest(['issue_id' => 'NB-1'])));

    expect($payload)->toMatchArray([
        'success' => true,
        'state' => 'Ready for Dev',
    ]);

    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Resolution']['value'] === null;
    });
});

it('Tag adds a tag via POST when remove is not set', function (): void {
    Http::fake(['*/issues/NB-1/tags*' => Http::response(['id' => 't-1', 'name' => 'qa'])]);

    $payload = decodeMcpResponse((new Tag)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'tag' => 'qa',
    ])));

    expect($payload)->toMatchArray([
        'success' => true,
        'action' => 'added',
    ]);
});

it('Tag removes a tag when remove=true', function (): void {
    Http::fakeSequence('*/issues/NB-1/tags*')
        ->push([['id' => 't-1', 'name' => 'qa']])
        ->push(null, 200);

    $payload = decodeMcpResponse((new Tag)->handle(new McpRequest([
        'issue_id' => 'NB-1',
        'tag' => 'qa',
        'remove' => true,
    ])));

    expect($payload['action'])->toBe('removed');
});

it('Link creates an issue link via the natural-language commands API', function (): void {
    Http::fake(['*/commands*' => Http::response(['ok' => true])]);

    $payload = decodeMcpResponse((new Link)->handle(new McpRequest([
        'from' => 'NB-1',
        'to' => 'NB-2',
        'type' => 'duplicates',
    ])));

    expect($payload)->toMatchArray([
        'success' => true,
        'from' => 'NB-1',
        'to' => 'NB-2',
        'link_type' => 'duplicates',
    ]);

    Http::assertSent(static fn ($request): bool =>
        $request->data()['query'] === 'duplicates NB-2'
    );
});
