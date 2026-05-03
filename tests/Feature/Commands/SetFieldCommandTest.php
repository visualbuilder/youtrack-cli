<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('coerces numeric strings before sending the value to YouTrack', function (): void {
    // Custom field discovery (called by IssueService::setCustomField first).
    Http::fake([
        '*/issues/NB-1*' => Http::sequence()
            ->push([
                'id' => '3-1',
                'idReadable' => 'NB-1',
                'customFields' => [
                    ['name' => 'Error Count', '$type' => 'SimpleIssueCustomField', 'value' => 0],
                ],
            ])
            ->push(['id' => '3-1', 'idReadable' => 'NB-1']),
    ]);

    expect(Artisan::call('youtrack:set-field', [
        'issue_id' => 'NB-1',
        'field_name' => 'Error Count',
        'field_value' => '42',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
        'field' => 'Error Count',
        'value' => 42,
    ]);

    Http::assertSent(static function ($request): bool {
        if ($request->method() !== 'POST') {
            return false;
        }
        $body = $request->data();

        return ($body['customFields'][0]['name'] ?? null) === 'Error Count'
            && ($body['customFields'][0]['value'] ?? null) === 42;
    });
});

it('wraps enum values as { name: ... } based on the field $type', function (): void {
    Http::fake([
        '*/issues/NB-1*' => Http::sequence()
            ->push([
                'id' => '3-1',
                'idReadable' => 'NB-1',
                'customFields' => [
                    ['name' => 'System Area', '$type' => 'SingleEnumIssueCustomField', 'value' => null],
                ],
            ])
            ->push(['id' => '3-1', 'idReadable' => 'NB-1']),
    ]);

    Artisan::call('youtrack:set-field', [
        'issue_id' => 'NB-1',
        'field_name' => 'System Area',
        'field_value' => 'Visual regression',
    ]);

    Http::assertSent(static function ($request): bool {
        if ($request->method() !== 'POST') {
            return false;
        }
        $body = $request->data();

        return ($body['customFields'][0]['value']['name'] ?? null) === 'Visual regression';
    });
});
