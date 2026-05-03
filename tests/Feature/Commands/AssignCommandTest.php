<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('sets the Assignee custom field to the supplied login', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:assign', [
        'issue_id' => 'NB-1',
        'assignee' => 'lee',
    ]))->toBe(0);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $body['customFields'][0]['name'] === 'Assignee'
            && $body['customFields'][0]['value']['login'] === 'lee';
    });
});

it('clears the Assignee field when --clear is passed', function (): void {
    Http::fake(['*/issues/NB-1*' => Http::response(['id' => '3-1', 'idReadable' => 'NB-1'])]);

    expect(Artisan::call('youtrack:assign', [
        'issue_id' => 'NB-1',
        '--clear' => true,
    ]))->toBe(0);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $body['customFields'][0]['name'] === 'Assignee'
            && $body['customFields'][0]['value'] === null;
    });
});

it('refuses to run with no login and no --clear flag', function (): void {
    expect(Artisan::call('youtrack:assign', ['issue_id' => 'NB-1']))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('--clear');
});
