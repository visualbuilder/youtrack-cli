<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('posts the StateIssueCustomField in a single request and emits success JSON', function (): void {
    Http::fake(['*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:update-state', [
        'issue_id' => 'NB-1',
        'state' => 'Code Review',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
        'new_state' => 'Code Review',
    ]);

    Http::assertSentCount(1);
    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/issues/NB-1')
        && $request->data()['customFields'][0]['value']['name'] === 'Code Review'
    );
});

it('emits the structured-error envelope when YouTrack rejects the state update', function (): void {
    config(['youtrack.http_retries' => 0]);
    Http::fake(['*' => Http::response('bad', 400)]);

    expect(Artisan::call('youtrack:update-state', [
        'issue_id' => 'NB-1',
        'state' => 'Bogus',
    ]))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'ok' => false,
        'status' => 502,
    ])->and($payload['error'])->toContain('Failed to update state');
});
