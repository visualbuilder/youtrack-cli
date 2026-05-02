<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Build a fake `/admin/projects` payload — the package only needs the
 * `shortName` and `customFields[].field.name` keys.
 *
 * @param  array<int, string>  $fieldNames
 */
function fakeProjectPayload(string $shortName, array $fieldNames): array
{
    return [[
        'shortName' => $shortName,
        'customFields' => array_map(
            static fn (string $name) => ['field' => ['name' => $name]],
            $fieldNames,
        ),
    ]];
}

it('returns ready=true with all tier-1 fields configured', function (): void {
    Http::fake(['*/admin/projects*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type', 'PR URL'])
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'project' => 'NB',
        'ready' => true,
    ])->and($payload['tier_1']['missing'])->toBe([])
        ->and($payload['tier_2']['configured'])->toBe(['PR URL'])
        ->and($payload['tier_2']['missing'])->toContain('Error Count');
});

it('returns ready=false and exit-code 1 when a tier-1 field is missing', function (): void {
    Http::fake(['*/admin/projects*' => Http::response(
        // Missing Type — only Status and Priority configured.
        fakeProjectPayload('NB', ['Status', 'Priority'])
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ready'])->toBeFalse()
        ->and($payload['tier_1']['missing'])->toBe(['Type']);
});

it('lists fields the package does not know about as extra_fields', function (): void {
    Http::fake(['*/admin/projects*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type', 'Pet Project Sponsor'])
    )]);

    Artisan::call('youtrack:check-project', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['extra_fields'])->toBe(['Pet Project Sponsor']);
});

it('reports an error when the project is not found', function (): void {
    Http::fake(['*/admin/projects*' => Http::response(
        // YouTrack returned other projects, not the one we asked for.
        fakeProjectPayload('OTHER', ['Status'])
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'error' => true,
        'project' => 'NB',
    ])->and($payload['message'])->toContain("Project 'NB' was not found");
});
