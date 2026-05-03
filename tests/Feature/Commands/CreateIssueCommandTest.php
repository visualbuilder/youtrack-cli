<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('creates an issue with the documented defaults (Bug / P3)', function (): void {
    Http::fake(['*/issues*' => Http::response(['idReadable' => 'NB-42', 'id' => '3-42'])]);

    expect(Artisan::call('youtrack:create-issue', [
        'project' => 'NB',
        'summary' => 'Test summary',
        'description' => 'Test description',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-42',
    ]);

    // Defaults must travel through to the wire — Type=Bug, Priority=P3.
    Http::assertSent(static function ($request): bool {
        $custom = collect($request->data()['customFields']);

        return $custom->firstWhere('name', 'Type')['value']['name'] === 'Bug'
            && $custom->firstWhere('name', 'Priority')['value']['name'] === 'P3';
    });
});

it('forwards explicit --type and --priority options to YouTrack', function (): void {
    Http::fake(['*/issues*' => Http::response(['idReadable' => 'NB-43', 'id' => '3-43'])]);

    expect(Artisan::call('youtrack:create-issue', [
        'project' => 'NB',
        'summary' => 'Enhancement',
        'description' => 'Body',
        '--type' => 'Enhancement',
        '--priority' => 'P1',
    ]))->toBe(0);

    Http::assertSent(static function ($request): bool {
        $custom = collect($request->data()['customFields']);

        return $custom->firstWhere('name', 'Type')['value']['name'] === 'Enhancement'
            && $custom->firstWhere('name', 'Priority')['value']['name'] === 'P1';
    });
});

it('reads defaults from config when --type and --priority are omitted', function (): void {
    // Host that uses a non-P-grade vocabulary: configure once, no need to pass
    // flags on every create. Proves the lookup chain works end-to-end through
    // the artisan command layer.
    config([
        'youtrack.priorities.default' => 'Critical',
        'youtrack.types.default' => 'Defect',
    ]);

    Http::fake(['*/issues*' => Http::response(['idReadable' => 'NB-44', 'id' => '3-44'])]);

    Artisan::call('youtrack:create-issue', [
        'project' => 'NB',
        'summary' => 'a',
        'description' => 'b',
    ]);

    Http::assertSent(static function ($request): bool {
        $custom = collect($request->data()['customFields']);

        return $custom->firstWhere('name', 'Type')['value']['name'] === 'Defect'
            && $custom->firstWhere('name', 'Priority')['value']['name'] === 'Critical';
    });
});
