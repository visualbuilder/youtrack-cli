<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('patches summary and description in a single request', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:update-issue', [
        'issue_id' => 'NB-1',
        '--summary' => 'New title',
        '--description' => 'New body',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
    ])->and($payload['updated'])->toBe(['summary', 'description']);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/issues/NB-1')
            && $body['summary'] === 'New title'
            && $body['description'] === 'New body';
    });
});

it('rejects calls that supply neither --summary nor --description', function (): void {
    expect(Artisan::call('youtrack:update-issue', ['issue_id' => 'NB-1']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('at least one');
});
