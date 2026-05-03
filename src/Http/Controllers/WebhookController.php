<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Visualbuilder\YoutrackCli\Events\YoutrackWebhookReceived;

/**
 * Inbound YouTrack webhook receiver. Verification (HMAC) is handled by the
 * VerifyYoutrackWebhook middleware — by the time this controller runs the
 * payload is trusted.
 *
 * Idempotency: YouTrack delivers the `X-YouTrack-Delivery-Id` header on
 * each call. We cache seen ids for 24h and 200-without-firing on replays.
 * Falls back to a body hash when the header is missing (some test rigs
 * don't send it).
 */
class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $deliveryId = (string) $request->header(
            'X-YouTrack-Delivery-Id',
            substr(hash('sha256', $request->getContent()), 0, 16),
        );

        $cacheKey = "youtrack-cli:webhook:delivery:{$deliveryId}";

        if (Cache::has($cacheKey)) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'delivery_id' => $deliveryId,
            ]);
        }

        Cache::put($cacheKey, true, now()->addDay());

        $payload = is_array($request->json()->all()) ? $request->json()->all() : [];

        YoutrackWebhookReceived::dispatch($deliveryId, $payload);

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'delivery_id' => $deliveryId,
        ]);
    }
}
