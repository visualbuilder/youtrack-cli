<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify inbound YouTrack webhook signatures. The shared secret lives in
 * `config('youtrack.webhook_secret')` — set the same value in the YouTrack
 * project's webhook config.
 *
 * If the secret isn't configured the middleware refuses every request,
 * because silently letting webhooks through unverified would be a footgun.
 *
 * Verification: HMAC-SHA256 of the raw body against the `X-YouTrack-Signature`
 * header, compared with `hash_equals` for timing-attack resistance.
 */
class VerifyYoutrackWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('youtrack.webhook_secret', '');

        if ($secret === '') {
            return response()->json([
                'ok' => false,
                'error' => 'youtrack.webhook_secret is not configured.',
            ], 401);
        }

        $provided = (string) $request->header('X-YouTrack-Signature', '');
        if ($provided === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Missing X-YouTrack-Signature header.',
            ], 401);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
