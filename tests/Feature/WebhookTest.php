<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Visualbuilder\YoutrackCli\Events\YoutrackWebhookReceived;

beforeEach(function (): void {
    config(['youtrack.webhook_secret' => 'shhh']);
});

function signedPost(string $body, string $deliveryId = 'd-1', string $secretOverride = 'shhh'): \Illuminate\Testing\TestResponse
{
    /** @var \Visualbuilder\YoutrackCli\Tests\TestCase $tc */
    $tc = test();

    $signature = hash_hmac('sha256', $body, $secretOverride);

    return $tc->call(
        method: 'POST',
        uri: '/youtrack/webhook',
        server: [
            'HTTP_X_YOUTRACK_SIGNATURE' => $signature,
            'HTTP_X_YOUTRACK_DELIVERY_ID' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $body,
    );
}

it('rejects requests with a missing signature', function (): void {
    $tc = test();
    $response = $tc->postJson('/youtrack/webhook', ['ping' => true]);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'Missing X-YouTrack-Signature header.');
});

it('rejects requests with an invalid signature', function (): void {
    $body = json_encode(['ping' => true]);

    $response = signedPost($body, secretOverride: 'wrong-secret');

    $response->assertStatus(401)
        ->assertJsonPath('error', 'Invalid webhook signature.');
});

it('rejects requests when no webhook_secret is configured', function (): void {
    config(['youtrack.webhook_secret' => null]);

    $body = json_encode(['ping' => true]);
    $response = signedPost($body);

    $response->assertStatus(401)
        ->assertJsonPath('error', 'youtrack.webhook_secret is not configured.');
});

it('accepts a valid signature and dispatches the YoutrackWebhookReceived event', function (): void {
    Event::fake([YoutrackWebhookReceived::class]);

    $body = json_encode(['issue' => ['idReadable' => 'NB-7'], 'event' => 'state-change']);

    $response = signedPost($body, deliveryId: 'd-7');

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'duplicate' => false,
            'delivery_id' => 'd-7',
        ]);

    Event::assertDispatched(
        YoutrackWebhookReceived::class,
        static fn (YoutrackWebhookReceived $e): bool =>
            $e->deliveryId === 'd-7'
            && ($e->payload['issue']['idReadable'] ?? null) === 'NB-7',
    );
});

it('returns ok+duplicate without re-firing the event on a replay', function (): void {
    Event::fake([YoutrackWebhookReceived::class]);

    $body = json_encode(['ping' => true]);

    signedPost($body, deliveryId: 'replay-1')->assertOk();
    signedPost($body, deliveryId: 'replay-1')
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    Event::assertDispatchedTimes(YoutrackWebhookReceived::class, 1);
});
