<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('returns matched issues keyed by fingerprint and counts non-null hits', function (): void {
    Http::fake(['*/issues*' => Http::response([
        [
            'id' => '3-1',
            'idReadable' => 'NB-1',
            'description' => 'crash details [error-fp:aaa] more text',
            'customFields' => [
                ['name' => 'Status', 'value' => ['name' => 'Open']],
                ['name' => 'Priority', 'value' => ['name' => 'P2']],
                ['name' => 'Type', 'value' => ['name' => 'Bug']],
                ['name' => 'Error Count', '$type' => 'SimpleIssueCustomField', 'value' => 17],
            ],
        ],
    ])]);

    expect(Artisan::call('youtrack:bulk-search-fingerprints', [
        'fingerprints' => json_encode(['aaa', 'bbb']),
        '--project' => 'NB',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'count' => 1,
        'project' => 'NB',
    ]);

    expect($payload['results']['aaa'])->toMatchArray([
        'issue_id' => 'NB-1',
        'state' => 'Open',
        'error_count' => 17,
    ])->and($payload['results']['bbb'])->toBeNull();
});

it('rejects non-JSON-array input via the structured-error envelope', function (): void {
    expect(Artisan::call('youtrack:bulk-search-fingerprints', [
        'fingerprints' => 'not-json',
    ]))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('Invalid JSON array');
});
