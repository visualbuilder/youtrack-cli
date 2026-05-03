<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('lists developer-approved issues with the configured state name', function (): void {
    config(['youtrack.states.developer_approved' => 'Developer Approved']);
    Http::fake(['*/issues*' => Http::response([])]);

    Artisan::call('youtrack:list-approved', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['state'])->toBe('Developer Approved');

    Http::assertSent(static fn ($request): bool =>
        str_contains(urldecode($request->url()), 'Status: {Developer Approved}')
    );
});
