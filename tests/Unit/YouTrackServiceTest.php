<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Visualbuilder\YoutrackCli\Services\YouTrackService;

it('appends /api to the configured base URL', function (): void {
    $service = app(YouTrackService::class);

    expect($service->baseUrl())->toBe('https://example.youtrack.cloud/api');
});

it('returns the configured default project', function (): void {
    expect(app(YouTrackService::class)->defaultProject())->toBe('NB');
});

it('reports as enabled when both base URL and token are set', function (): void {
    expect(app(YouTrackService::class)->isEnabled())->toBeTrue();
});

it('throws when no base URL is configured', function (): void {
    config(['youtrack.base_url' => null]);

    app(YouTrackService::class)->baseUrl();
})->throws(RuntimeException::class, 'YouTrack base URL is not configured');

it('throws when no token is configured and the http client is requested', function (): void {
    config(['youtrack.token' => null]);

    app(YouTrackService::class)->http();
})->throws(RuntimeException::class, 'YouTrack integration is not configured');

it('sends a Bearer auth header on outbound requests', function (): void {
    Http::fake();

    app(YouTrackService::class)->http()->get('issues');

    Http::assertSent(static fn ($request) =>
        $request->hasHeader('Authorization', 'Bearer perm:test-token')
    );
});

it('resolves state names from config with a fallback', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);

    $service = app(YouTrackService::class);

    expect($service->stateName('ready'))->toBe('Ready for Dev')
        ->and($service->stateName('unknown_key'))->toBe('unknown_key');
});
