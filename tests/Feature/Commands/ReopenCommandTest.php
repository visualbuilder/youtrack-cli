<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('clears Resolution and moves Status back to the configured open state', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:reopen', ['issue_id' => 'NB-1']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'state' => 'Ready for Dev',
    ]);

    Http::assertSent(static function ($request): bool {
        $byName = collect($request->data()['customFields'])->keyBy('name');

        return $byName['Status']['value']['name'] === 'Ready for Dev'
            && $byName['Resolution']['value'] === null;
    });
});

it('honours --state override', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    Artisan::call('youtrack:reopen', [
        'issue_id' => 'NB-1',
        '--state' => 'In Progress',
    ]);

    Http::assertSent(static fn ($request): bool =>
        collect($request->data()['customFields'])
            ->keyBy('name')['Status']['value']['name'] === 'In Progress'
    );
});
