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

it('throws when the active connection has no base URL configured', function (): void {
    config(['youtrack.connections.default.base_url' => null]);

    app(YouTrackService::class)->baseUrl();
})->throws(RuntimeException::class, 'YouTrack base URL is not configured');

it('throws when the active connection has no token configured', function (): void {
    config(['youtrack.connections.default.token' => null]);

    app(YouTrackService::class)->http();
})->throws(RuntimeException::class, "connection 'default' is not configured");

it('sends a Bearer auth header on outbound requests', function (): void {
    Http::fake();

    app(YouTrackService::class)->http()->get('issues');

    Http::assertSent(static fn ($request) =>
        $request->hasHeader('Authorization', 'Bearer perm:test-token')
    );
});

it('routes per-connection credentials when --instance picks a non-default workspace', function (): void {
    config([
        'youtrack.connections.support' => [
            'base_url' => 'https://support.youtrack.cloud',
            'token' => 'perm:support-token',
            'default_project' => 'SUPP',
        ],
    ]);

    $support = (new \Visualbuilder\YoutrackCli\Services\YouTrackService())->on('support');

    expect($support->baseUrl())->toBe('https://support.youtrack.cloud/api')
        ->and($support->defaultProject())->toBe('SUPP')
        ->and($support->connectionName())->toBe('support');
});

it('refuses to build an http client when the active connection has no credentials', function (): void {
    // Wipe the test default — the resolver should fail loudly, not silently
    // pick up some other set of keys, when the named connection is missing.
    config(['youtrack.connections' => []]);

    app(\Visualbuilder\YoutrackCli\Services\YouTrackService::class)->http();
})->throws(RuntimeException::class, "connection 'default' is not configured");

it('resolves state names from config with a fallback', function (): void {
    config(['youtrack.states.ready' => 'Ready for Dev']);

    $service = app(YouTrackService::class);

    expect($service->stateName('ready'))->toBe('Ready for Dev')
        ->and($service->stateName('unknown_key'))->toBe('unknown_key');
});

it('exposes configured priority and type defaults + value whitelists', function (): void {
    config([
        'youtrack.priorities' => [
            'default' => 'Critical',
            'values' => ['Critical', 'Major', 'Normal', 'Minor'],
        ],
        'youtrack.types' => [
            'default' => 'Defect',
            'values' => ['Defect', 'Story', 'Spike'],
        ],
    ]);

    $svc = app(YouTrackService::class);

    expect($svc->defaultPriority())->toBe('Critical')
        ->and($svc->priorityValues())->toBe(['Critical', 'Major', 'Normal', 'Minor'])
        ->and($svc->defaultType())->toBe('Defect')
        ->and($svc->typeValues())->toBe(['Defect', 'Story', 'Spike']);
});

it('returns an empty values array when priority/type whitelist is unset', function (): void {
    config([
        'youtrack.priorities' => ['default' => 'P3', 'values' => []],
        'youtrack.types' => ['default' => 'Bug', 'values' => []],
    ]);

    $svc = app(YouTrackService::class);

    expect($svc->priorityValues())->toBe([])
        ->and($svc->typeValues())->toBe([]);
});
