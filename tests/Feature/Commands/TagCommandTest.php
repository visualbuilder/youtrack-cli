<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('adds a tag via POST /issues/{id}/tags', function (): void {
    Http::fake(['*/issues/NB-1/tags*' => Http::response(['id' => 't-1', 'name' => 'visual-regression'])]);

    expect(Artisan::call('youtrack:tag', [
        'issue_id' => 'NB-1',
        'tag' => 'visual-regression',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'tag' => 'visual-regression',
        'action' => 'added',
    ]);

    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/issues/NB-1/tags')
        && $request->data()['name'] === 'visual-regression'
    );
});

it('removes a tag by resolving its id then DELETEing it', function (): void {
    Http::fakeSequence('*/issues/NB-1/tags*')
        ->push([['id' => 't-7', 'name' => 'visual-regression']])
        ->push(null, 200);

    expect(Artisan::call('youtrack:tag', [
        'issue_id' => 'NB-1',
        'tag' => 'visual-regression',
        '--remove' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);
    expect($payload['action'])->toBe('removed');

    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/api/issues/NB-1/tags/t-7')
    );
});

it('reports an error when removing a tag that is not on the issue', function (): void {
    Http::fake(['*/issues/NB-1/tags*' => Http::response([])]);

    expect(Artisan::call('youtrack:tag', [
        'issue_id' => 'NB-1',
        'tag' => 'missing',
        '--remove' => true,
    ]))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);
    expect($payload['error'])->toContain("Tag 'missing' is not on issue NB-1");
});
