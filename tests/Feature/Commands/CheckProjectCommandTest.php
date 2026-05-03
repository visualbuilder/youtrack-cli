<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/**
 * Build a fake `/admin/projects/{id}` payload — the package only needs the
 * `shortName` and `customFields[].field.name` keys. The endpoint returns a
 * single project object now, not an array.
 *
 * @param  array<int, string>  $fieldNames
 */
function fakeProjectPayload(string $shortName, array $fieldNames): array
{
    return [
        'shortName' => $shortName,
        'customFields' => array_map(
            static fn (string $name) => ['field' => ['name' => $name]],
            $fieldNames,
        ),
    ];
}

it('returns ready=true with stock required fields configured', function (): void {
    // No host-recommended fields — covers the "out of the box, just stock
    // YouTrack" case where everything required is present.
    config(['youtrack.fields.recommended' => []]);

    Http::fake(['*/admin/projects/NB*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type'])
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'project' => 'NB',
        'ready' => true,
    ])->and($payload['required']['missing'])->toBe([])
        ->and($payload['recommended']['configured'])->toBe([])
        ->and($payload['recommended']['missing'])->toBe([]);
});

it('reports configured + missing host-recommended fields', function (): void {
    config([
        'youtrack.fields.recommended' => ['PR URL', 'Error Count', 'System Area'],
    ]);

    Http::fake(['*/admin/projects/NB*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type', 'PR URL'])
    )]);

    Artisan::call('youtrack:check-project', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['recommended']['configured'])->toBe(['PR URL'])
        ->and($payload['recommended']['missing'])->toBe(['Error Count', 'System Area']);
});

it('returns ready=false and exit-code 1 when a required field is missing', function (): void {
    Http::fake(['*/admin/projects/NB*' => Http::response(
        // Type missing — only Status and Priority configured.
        fakeProjectPayload('NB', ['Status', 'Priority'])
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ready'])->toBeFalse()
        ->and($payload['required']['missing'])->toBe(['Type']);
});

it('lists fields outside both buckets as extra_fields', function (): void {
    config(['youtrack.fields.recommended' => ['PR URL']]);

    Http::fake(['*/admin/projects/NB*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type', 'PR URL', 'Pet Project Sponsor'])
    )]);

    Artisan::call('youtrack:check-project', ['--project' => 'NB']);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['extra_fields'])->toBe(['Pet Project Sponsor']);
});

it('honours custom required-field overrides', function (): void {
    // Hosts whose YouTrack project requires extra fields beyond Status /
    // Priority / Type can extend the `required` list. Not contrived — some
    // tenants enforce custom fields per project policy.
    config([
        'youtrack.fields.required' => ['Status', 'Priority', 'Type', 'Severity'],
        'youtrack.fields.recommended' => [],
    ]);

    Http::fake(['*/admin/projects/NB*' => Http::response(
        fakeProjectPayload('NB', ['Status', 'Priority', 'Type'])  // no Severity
    )]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['required']['missing'])->toBe(['Severity']);
});

it('reports an error when the project is not found', function (): void {
    config(['youtrack.http_retries' => 0]);

    Http::fake(['*/admin/projects/NB*' => Http::response('Not found', 404)]);

    expect(Artisan::call('youtrack:check-project', ['--project' => 'NB']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'ok' => false,
        'project' => 'NB',
    ])->and($payload['error'])->toContain("Project 'NB' was not found");
});
