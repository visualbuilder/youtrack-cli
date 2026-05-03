<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('creates an issue link via the commands endpoint with the natural-language query', function (): void {
    Http::fake(['*/commands*' => Http::response(['ok' => true])]);

    expect(Artisan::call('youtrack:link', [
        'from' => 'NB-1',
        'to' => 'NB-2',
        '--type' => 'duplicates',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'from' => 'NB-1',
        'to' => 'NB-2',
        'link_type' => 'duplicates',
    ]);

    Http::assertSent(static function ($request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/commands')
            && $body['query'] === 'duplicates NB-2'
            && $body['issues'][0]['idReadable'] === 'NB-1';
    });
});

it('defaults to the "depends on" link type when --type is omitted', function (): void {
    Http::fake(['*/commands*' => Http::response(['ok' => true])]);

    Artisan::call('youtrack:link', ['from' => 'NB-1', 'to' => 'NB-2']);

    Http::assertSent(static fn ($request): bool =>
        $request->data()['query'] === 'depends on NB-2'
    );
});
