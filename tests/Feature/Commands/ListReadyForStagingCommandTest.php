<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('lists ready-for-staging issues with the configured state name', function (): void {
    config(['youtrack.states.ready_for_staging' => 'Ready for Staging']);
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-ready-for-staging', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Ready for Staging');

    Http::assertSent(static fn ($request): bool =>
        str_contains(urldecode($request->url()), 'Status: {Ready for Staging}')
    );
});
