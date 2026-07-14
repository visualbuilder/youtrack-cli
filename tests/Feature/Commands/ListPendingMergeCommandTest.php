<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('lists pending-merge issues with the configured state name', function (): void {
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-pending-merge', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Approved - pending manual merge');

    Http::assertSent(static fn ($request): bool =>
        str_contains(urldecode($request->url()), 'Status: {Approved - pending manual merge}')
    );
});

it('honours the env-configurable state name', function (): void {
    config(['youtrack.states.pending_merge' => 'Custom Merge State']);
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-pending-merge');

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Custom Merge State');
});
