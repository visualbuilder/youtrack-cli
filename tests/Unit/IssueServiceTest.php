<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Visualbuilder\YoutrackCli\Services\IssueService;

beforeEach(function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);
});

/**
 * Build a typical YouTrack /issues response payload — one ticket with a couple
 * of custom fields, used by listByState / search to test normalisation.
 */
function fakeIssuePayload(string $idReadable = 'NB-101', string $summary = 'Ticket'): array
{
    return [
        'id' => '3-1001',
        'idReadable' => $idReadable,
        'summary' => $summary,
        'description' => 'A test ticket.',
        'created' => 1_700_000_000_000,
        'updated' => 1_700_000_000_000,
        'customFields' => [
            ['name' => 'Status', 'value' => ['name' => 'Ready for Dev']],
            ['name' => 'Priority', 'value' => ['name' => 'P3']],
            ['name' => 'Type', 'value' => ['name' => 'Bug']],
        ],
    ];
}

it('lists issues by state and normalises the response', function (): void {
    Http::fake([
        '*/issues*' => Http::response([fakeIssuePayload()]),
    ]);

    $issues = app(IssueService::class)->listByState('Ready for Dev');

    expect($issues)->toHaveCount(1)
        ->and($issues->first())->toMatchArray([
            'id' => 'NB-101',
            'summary' => 'Ticket',
            'state' => 'Ready for Dev',
            'priority' => 'P3',
            'type' => 'Bug',
        ]);

    Http::assertSent(static fn ($request) =>
        str_contains($request->url(), '/api/issues')
        && str_contains(urldecode($request->url()), 'project: NB')
    );
});

it('listReady delegates to listByState with the configured ready state', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    app(IssueService::class)->listReady();

    Http::assertSent(static fn ($request) =>
        str_contains(urldecode($request->url()), 'Status: {Ready for Dev}')
    );
});

it('throws a RuntimeException when YouTrack returns a 5xx', function (): void {
    // Disable HTTP retries for this test — the production retry() wrapper
    // turns failures into Illuminate RequestExceptions, which would mask
    // the service's own RuntimeException. Tests the explicit-failure path.
    config(['youtrack.http_retries' => 0]);

    Http::fake(['*/issues*' => Http::response('boom', 500)]);

    app(IssueService::class)->listByState('Ready for Dev');
})->throws(RuntimeException::class, 'Failed to list issues');

it('creates an issue with a structured request body', function (): void {
    Http::fake([
        '*/issues*' => Http::response(['id' => '3-2001', 'idReadable' => 'NB-200']),
    ]);

    $result = app(IssueService::class)->createIssue(
        project: 'NB',
        summary: 'New ticket',
        description: 'Body',
        type: 'Bug',
        priority: 'P3',
    );

    expect($result)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-200',
    ]);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/issues')
            && $body['project']['shortName'] === 'NB'
            && $body['summary'] === 'New ticket'
            && collect($body['customFields'])->firstWhere('name', 'Type')['value']['name'] === 'Bug'
            && collect($body['customFields'])->firstWhere('name', 'Priority')['value']['name'] === 'P3';
    });
});

it('searches free-text and returns normalised issues', function (): void {
    Http::fake(['*/issues*' => Http::response([fakeIssuePayload('NB-7', 'Search hit')])]);

    $hits = app(IssueService::class)->search('hit');

    expect($hits)->toHaveCount(1)
        ->and($hits->first()['summary'])->toBe('Search hit');
});

it('search queries both summary and description', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    app(IssueService::class)->search('hit');

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'summary: {hit}')
            && str_contains($url, 'description: {hit}')
            && str_contains($url, ' or ');
    });
});

it('updateState posts the StateIssueCustomField in a single round trip', function (): void {
    // Track how many requests we send. Old behaviour was 2 (GET issue + POST
    // patch). The single-round-trip refactor cuts it to 1.
    Http::fake(['*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'], 200)]);

    $result = app(IssueService::class)->updateState('NB-1', 'Done');

    expect($result)->toMatchArray([
        'issue_id' => 'NB-1',
        'new_state' => 'Done',
        'success' => true,
    ]);

    Http::assertSentCount(1);
    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/issues/NB-1')
            && $body['customFields'][0]['name'] === 'Status'
            && $body['customFields'][0]['$type'] === 'StateIssueCustomField'
            && $body['customFields'][0]['value']['name'] === 'Done';
    });
});

it('getProjectFields hits admin/projects/{id} directly and unwraps custom field names', function (): void {
    Http::fake([
        '*/admin/projects/NB*' => Http::response([
            'shortName' => 'NB',
            'customFields' => [
                ['field' => ['name' => 'Status']],
                ['field' => ['name' => 'Priority']],
                ['field' => ['name' => 'Type']],
            ],
        ]),
    ]);

    $fields = app(IssueService::class)->getProjectFields('NB');

    expect($fields->all())->toBe(['Status', 'Priority', 'Type']);

    Http::assertSent(static fn ($request): bool =>
        str_contains($request->url(), '/api/admin/projects/NB')
    );
});

it('getProjectFields surfaces a clear error when the project 404s', function (): void {
    config(['youtrack.http_retries' => 0]);

    Http::fake(['*/admin/projects/*' => Http::response('Not found', 404)]);

    app(IssueService::class)->getProjectFields('GHOST');
})->throws(RuntimeException::class, "Project 'GHOST' was not found in YouTrack.");

it('updateIssue patches summary and description in one POST and reports which fields changed', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $result = app(IssueService::class)->updateIssue('NB-1', summary: 'New', description: 'Body');

    expect($result)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
        'updated' => ['summary', 'description'],
    ]);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/issues/NB-1')
            && $body['summary'] === 'New'
            && $body['description'] === 'Body';
    });
});

it('updateIssue refuses to run when neither summary nor description is supplied', function (): void {
    app(IssueService::class)->updateIssue('NB-1');
})->throws(RuntimeException::class, 'updateIssue requires at least one');

it('assignIssue sets the SingleUserIssueCustomField with the supplied login', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $result = app(IssueService::class)->assignIssue('NB-1', 'lee');

    expect($result)->toMatchArray(['success' => true, 'assignee' => 'lee']);

    Http::assertSent(static function ($request): bool {
        $field = $request->data()['customFields'][0];

        return $field['name'] === 'Assignee'
            && $field['$type'] === 'SingleUserIssueCustomField'
            && $field['value']['login'] === 'lee';
    });
});

it('assignIssue clears the Assignee field when login is null', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    app(IssueService::class)->assignIssue('NB-1', null);

    Http::assertSent(static fn ($request): bool =>
        $request->data()['customFields'][0]['value'] === null
    );
});

it('addTag posts the tag name to the issue tags endpoint', function (): void {
    Http::fake(['*/issues/NB-1/tags*' => Http::response(['id' => 't-1', 'name' => 'visual-regression'])]);

    $result = app(IssueService::class)->addTag('NB-1', 'visual-regression');

    expect($result)->toMatchArray(['success' => true, 'action' => 'added', 'tag' => 'visual-regression']);

    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/issues/NB-1/tags')
        && $request->data()['name'] === 'visual-regression'
    );
});

it('removeTag resolves the tag id then DELETEs it', function (): void {
    Http::fakeSequence('*/issues/NB-1/tags*')
        ->push([
            ['id' => 't-7', 'name' => 'visual-regression'],
            ['id' => 't-9', 'name' => 'other'],
        ])
        ->push(null, 200);

    $result = app(IssueService::class)->removeTag('NB-1', 'visual-regression');

    expect($result['action'])->toBe('removed');

    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/issues/NB-1/tags/t-7')
    );
});

it('removeTag throws when the tag is not on the issue', function (): void {
    Http::fake(['*/issues/NB-1/tags*' => Http::response([])]);

    app(IssueService::class)->removeTag('NB-1', 'absent');
})->throws(RuntimeException::class, "Tag 'absent' is not on issue NB-1");

it('linkIssues posts a natural-language command via /commands', function (): void {
    Http::fake(['*/commands*' => Http::response(['ok' => true])]);

    $result = app(IssueService::class)->linkIssues('NB-1', 'NB-2', 'duplicates');

    expect($result)->toMatchArray([
        'success' => true,
        'from' => 'NB-1',
        'to' => 'NB-2',
        'link_type' => 'duplicates',
    ]);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/commands')
            && $body['query'] === 'duplicates NB-2'
            && $body['issues'][0]['idReadable'] === 'NB-1';
    });
});

it('resolveIssue sets Status and Resolution atomically in a single POST', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    $result = app(IssueService::class)->resolveIssue('NB-1', 'Fixed', 'Done');

    expect($result)->toMatchArray([
        'success' => true,
        'state' => 'Done',
        'resolution' => 'Fixed',
    ]);

    Http::assertSentCount(1);
    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Status']['value']['name'] === 'Done'
            && $byName['Resolution']['value']['name'] === 'Fixed';
    });
});

it('reopenIssue clears Resolution and resets Status to the supplied open state', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    app(IssueService::class)->reopenIssue('NB-1', 'Ready for Dev');

    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Status']['value']['name'] === 'Ready for Dev'
            && $byName['Resolution']['value'] === null;
    });
});

it('query paginates via $skip/$top and supports a project scope override', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    app(IssueService::class)->query('assignee: me', 'NB', page: 3, perPage: 25);

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'project: NB assignee: me')
            && str_contains($url, '$skip=50')   // (3-1) * 25
            && str_contains($url, '$top=25');
    });
});

it('formatTimestamp emits UTC regardless of the server timezone', function (): void {
    // Force a non-UTC server TZ — date('...Z') would return wall-clock New
    // York time with a misleading `Z` suffix. gmdate must always return the
    // genuine UTC instant.
    $previous = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    try {
        Http::fake([
            '*/issues/NB-1/comments*' => Http::response([]),
            '*/issues/NB-1*' => Http::response([
                'id' => '3-1',
                'idReadable' => 'NB-1',
                'summary' => 't',
                'description' => '',
                'created' => 1_700_000_000_000,   // 2023-11-14T22:13:20Z
                'updated' => 1_700_000_000_000,
                'customFields' => [],
            ]),
        ]);

        $issue = app(IssueService::class)->getIssue('NB-1');

        expect($issue['created'])->toBe('2023-11-14T22:13:20Z')
            ->and($issue['updated'])->toBe('2023-11-14T22:13:20Z');
    } finally {
        date_default_timezone_set($previous);
    }
});
