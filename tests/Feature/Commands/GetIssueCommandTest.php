<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('returns a normalised issue with comments', function (): void {
    Http::fake([
        '*/issues/NB-1/comments*' => Http::response([
            ['id' => 'c1', 'text' => 'first', 'author' => ['login' => 'lee'], 'created' => 1_700_000_000_000, 'updated' => 1_700_000_000_000],
        ]),
        '*/issues/NB-1*' => Http::response([
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'summary' => 'Subject',
            'description' => 'Body',
            'created' => 1_700_000_000_000,
            'updated' => 1_700_000_000_000,
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Open']],
                ['name' => 'Priority', 'value' => ['name' => 'P2']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
            ],
        ]),
    ]);

    expect(Artisan::call('youtrack:get-issue', ['issue' => 'NB-1']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'id' => 'NB-1',
        'summary' => 'Subject',
        'state' => 'Open',
        'priority' => 'P2',
        'type' => 'Bug',
    ])->and($payload['comments'])->toHaveCount(1);
});

it('emits the structured-error envelope when the upstream returns 5xx', function (): void {
    config(['youtrack.http_retries' => 0]);
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(Artisan::call('youtrack:get-issue', ['issue' => 'NB-1']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'ok' => false,
        'status' => 502,
    ])->and($payload['error'])->toContain('Failed to');
});
