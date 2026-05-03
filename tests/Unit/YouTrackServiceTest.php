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

it('falls back to the legacy top-level base_url/token shim for the default connection', function (): void {
    // Top-level keys only — no `youtrack.connections.default` configured.
    // Resolver should still find the credentials via the shim.
    expect(app(\Visualbuilder\YoutrackCli\Services\YouTrackService::class)->baseUrl())
        ->toBe('https://example.youtrack.cloud/api');
});

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
