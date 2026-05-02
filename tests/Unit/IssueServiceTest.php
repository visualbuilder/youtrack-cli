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
