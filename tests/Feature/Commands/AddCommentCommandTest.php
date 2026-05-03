<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('posts a markdown comment and returns success JSON', function (): void {
    Http::fake(['*/issues/NB-1/comments*' => Http::response([
        'id' => '7-99',
        'text' => 'Hello',
        'created' => 1_700_000_000_000,
        'author' => ['login' => 'agent'],
    ])]);

    expect(Artisan::call('youtrack:add-comment', [
        'issue_id' => 'NB-1',
        'comment' => 'Implementation Complete — see PR #42.',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload)->toMatchArray([
        'success' => true,
        'issue_id' => 'NB-1',
    ]);

    Http::assertSent(static fn ($request): bool =>
        $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/issues/NB-1/comments')
        && $request->data()['text'] === 'Implementation Complete — see PR #42.'
    );
});

it('returns the structured-error envelope on a 4xx', function (): void {
    config(['youtrack.http_retries' => 0]);
    Http::fake(['*' => Http::response('forbidden', 403)]);

    expect(Artisan::call('youtrack:add-comment', [
        'issue_id' => 'NB-1',
        'comment' => 'whatever',
    ]))->toBe(1);

    $payload = json_decode(trim(Artisan::output()), true);

    expect($payload['ok'])->toBeFalse()
        ->and($payload['error'])->toContain('Failed to add comment');
});
