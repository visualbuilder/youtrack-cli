<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('outputs JSON with count and issues for youtrack:list-ready', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);

    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'summary' => 'First',
            'customFields' => [
                ['name' => 'Priority', 'value' => ['name' => 'P3']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
            ],
        ],
    ])]);

    expect(Artisan::call('youtrack:list-ready', ['--project' => 'NB']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'count' => 1,
        'project' => 'NB',
        'state' => 'Ready for Dev',
    ])->and($payload['issues'][0]['id'])->toBe('NB-1');
});

it('returns count=0 when YouTrack reports no matching issues', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    expect(Artisan::call('youtrack:list-ready', ['--project' => 'NB']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['count'])->toBe(0)
        ->and($payload['issues'])->toBe([]);
});
