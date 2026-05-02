<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Base service for YouTrack API integration.
 * Provides authentication and HTTP client configuration.
 */
class YouTrackService
{
    /**
     * Check if YouTrack integration is configured.
     */
    public function isEnabled(): bool
    {
        return $this->hasCredentials();
    }

    /**
     * Get HTTP client configured with authentication token.
     */
    public function http(): PendingRequest
    {
        if (! $this->hasCredentials()) {
            throw new RuntimeException('YouTrack integration is not configured.');
        }

        $timeout = max(5, (int) config('youtrack.http_timeout', 30));
        $retries = max(0, (int) config('youtrack.http_retries', 2));
        $delay = max(0, (int) config('youtrack.http_retry_delay', 250));

        $request = Http::baseUrl($this->baseUrl())
            ->timeout($timeout)
            ->withToken($this->token(), 'Bearer')
            ->acceptJson()
            ->asJson();

        if ($retries > 0) {
            $request = $request->retry($retries, $delay);
        }

        return $request;
    }

    /**
     * Get the base URL for the YouTrack API.
     */
    public function baseUrl(): string
    {
        $url = config('youtrack.base_url');

        if (! $url) {
            throw new RuntimeException('YouTrack base URL is not configured.');
        }

        return rtrim((string) $url, '/') . '/api';
    }

    /**
     * Get the default project ID.
     */
    public function defaultProject(): string
    {
        return (string) config('youtrack.default_project', 'NB');
    }

    /**
     * Get the state name for a given state key.
     *
     * @param  string  $key  One of: ready, in_progress, blocked, done
     */
    public function stateName(string $key): string
    {
        return (string) config("youtrack.states.{$key}", $key);
    }

    /**
     * Check if credentials are configured.
     */
    protected function hasCredentials(): bool
    {
        return (bool) ($this->baseUrl() && $this->token());
    }

    /**
     * Get the API token.
     */
    protected function token(): ?string
    {
        return config('youtrack.token');
    }
}
