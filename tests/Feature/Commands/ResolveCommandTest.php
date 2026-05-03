<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('sets Status and Resolution in a single atomic POST', function (): void {
    config(['youtrack.states.done' => 'Done']);
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:resolve', [
        'issue_id' => 'NB-1',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
        'state' => 'Done',
        'resolution' => 'Fixed',
    ]);

    Http::assertSent(static function ($request): bool {
        $fields = $request->data()['customFields'];
        $byName = collect($fields)->keyBy('name');

        return $byName['Status']['value']['name'] === 'Done'
            && $byName['Resolution']['value']['name'] === 'Fixed';
    });
});

it('emits the structured-error envelope when YouTrack rejects the resolution', function (): void {
    config(['youtrack.http_retries' => 0]);
    Http::fake(['*' => Http::response('not allowed', 403)]);

    expect(Artisan::call('youtrack:resolve', ['issue_id' => 'NB-1']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('Failed to resolve');
});

it('honours --as and --state overrides', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    Artisan::call('youtrack:resolve', [
        'issue_id' => 'NB-1',
        '--as' => 'Duplicate',
        '--state' => 'Closed',
    ]);

    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Status']['value']['name'] === 'Closed'
            && $byName['Resolution']['value']['name'] === 'Duplicate';
    });
});
