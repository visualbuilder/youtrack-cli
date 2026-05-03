<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a verified YouTrack webhook is received. Hosts subscribe
 * via Laravel's standard event system; the package itself ships zero default
 * behaviour — what to do with the event is host-side concern.
 */
class YoutrackWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  Decoded JSON body from YouTrack
     */
    public function __construct(
        public readonly string $deliveryId,
        public readonly array $payload,
    ) {}
}
