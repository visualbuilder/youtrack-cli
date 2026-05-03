<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('lists blocked issues and supports --query + pagination flags', function (): void {
    config(['youtrack.states.blocked' => 'Plan Review']);
    Http::fake(['*/issues*' => Http::response([])]);

    expect(Artisan::call('youtrack:list-blocked', [
        '--project' => 'NB',
        '--query' => 'assignee: agent',
        '--page' => '2',
        '--per-page' => '50',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'count' => 0,
        'project' => 'NB',
        'state' => 'Plan Review',
        'page' => 2,
        'per_page' => 50,
    ]);

    Http::assertSent(static function ($request): bool {
        $url = urldecode($request->url());

        return str_contains($url, 'project: NB Status: {Plan Review} assignee: agent')
            && str_contains($url, '$skip=50')
            && str_contains($url, '$top=50');
    });
});
