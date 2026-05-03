<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('runs a raw YQL query and returns paginated normalised issues', function (): void {
    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'summary' => 'Q hit',
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Open']],
                ['name' => 'Priority', 'value' => ['name' => 'P3']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
            ],
        ],
    ])]);

    expect(Artisan::call('youtrack:query', [
        'query' => 'assignee: me',
        '--project' => 'NB',
        '--per-page' => '5',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'count' => 1,
        'project' => 'NB',
        'query' => 'assignee: me',
        'page' => 1,
        'per_page' => 5,
        'next_page' => null,
    ])->and($payload['issues'][0]['summary'])->toBe('Q hit');

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'project: NB assignee: me')
            && str_contains($url, '$top=5');
    });
});

it('signals more results via next_page when the page is full', function (): void {
    // Return exactly $perPage rows — caller must assume there is another page.
    Http::fake(['*/issues*' => Http::response(array_fill(0, 5, [
        'id' => '3-1', 'idReadable' => 'NB-1', 'summary' => 's',
        'customFields' => [],
    ]))]);

    Artisan::call('youtrack:query', [
        'query' => '#Unresolved',
        '--per-page' => '5',
    ]);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['next_page'])->toBe(2)
        ->and($payload['per_page'])->toBe(5);
});
