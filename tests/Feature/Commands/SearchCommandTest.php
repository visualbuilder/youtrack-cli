<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('queries summary OR description and returns the count', function (): void {
    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'summary' => 'Match',
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Open']],
                ['name' => 'Priority', 'value' => ['name' => 'P3']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
            ],
        ],
    ])]);

    expect(Artisan::call('youtrack:search', [
        'query' => 'login',
        '--project' => 'NB',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'count' => 1,
        'project' => 'NB',
        'query' => 'login',
    ]);

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'summary: {login}')
            && str_contains($url, 'description: {login}')
            && str_contains($url, ' or ');
    });
});

it('emits the structured-error envelope when YouTrack rejects the search', function (): void {
    config(['youtrack.http_retries' => 0]);
    Http::fake(['*' => Http::response('bad query', 400)]);

    expect(Artisan::call('youtrack:search', ['query' => 'foo']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('Failed to');
});
