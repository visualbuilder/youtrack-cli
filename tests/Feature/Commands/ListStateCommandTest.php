<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('lists issues in an arbitrary literal state', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-state', [
        'state' => 'Approved - pending manual merge',
        '--project' => 'NB',
    ]);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Approved - pending manual merge');

    Http::assertSent(static fn ($request): bool =>
        str_contains(urldecode($request->url()), 'Status: {Approved - pending manual merge}')
    );
});

it('does not consult the states config', function (): void {
    config(['youtrack.states' => []]);
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-state', ['state' => 'Preview']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Preview');
});
