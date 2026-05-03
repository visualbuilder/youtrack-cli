<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Base service for YouTrack API integration.
 *
 * Multi-instance: the package supports a `youtrack.connections.NAME` map
 * so a single host can talk to several YouTrack workspaces. Hosts that
 * still use the legacy top-level `youtrack.base_url` / `youtrack.token`
 * keys keep working — the resolver falls back to those as the implicit
 * `default` connection (no migration required, ever).
 */
class YouTrackService
{
    private string $connection;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection
            ?? (string) config('youtrack.default_connection', 'default');
    }

    /**
     * Return a new service instance bound to a different named connection.
     * The original instance is left untouched — useful for one-off calls
     * against a non-default workspace from inside long-lived code that
     * usually wants the default.
     */
    public function on(string $connection): static
    {
        return new static($connection);
    }

    public function isEnabled(): bool
    {
        return $this->hasCredentials();
    }

    public function http(): PendingRequest
    {
        if (! $this->hasCredentials()) {
            throw new RuntimeException("YouTrack connection '{$this->connection}' is not configured.");
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

    public function baseUrl(): string
    {
        $url = $this->connectionConfig('base_url') ?? config('youtrack.base_url');

        if (! $url) {
            throw new RuntimeException("YouTrack base URL is not configured for connection '{$this->connection}'.");
        }

        return rtrim((string) $url, '/') . '/api';
    }

    public function defaultProject(): string
    {
        return (string) (
            $this->connectionConfig('default_project')
            ?? config('youtrack.default_project', 'NB')
        );
    }

    /**
     * Get the configured state name for a given state key. State names are
     * always read from the top-level `youtrack.states` map — connection-
     * scoping them is unnecessary because the dev-agent's lifecycle is
     * host-wide, not per-workspace.
     */
    public function stateName(string $key): string
    {
        return (string) config("youtrack.states.{$key}", $key);
    }

    /**
     * Default issue priority used when callers don't override. Backed by
     * `config('youtrack.priorities.default')` so hosts that use a non-
     * P-grade vocabulary (Critical / Major / etc.) configure once.
     */
    public function defaultPriority(): string
    {
        return (string) config('youtrack.priorities.default', 'P3');
    }

    /**
     * Optional whitelist of accepted priority names — drives MCP enums and
     * any host-level validators. Empty array means "no constraint".
     *
     * @return array<int, string>
     */
    public function priorityValues(): array
    {
        $values = config('youtrack.priorities.values', []);

        return is_array($values) ? array_values(array_filter($values)) : [];
    }

    /**
     * Default issue type used when callers don't override.
     */
    public function defaultType(): string
    {
        return (string) config('youtrack.types.default', 'Bug');
    }

    /**
     * Optional whitelist of accepted type names.
     *
     * @return array<int, string>
     */
    public function typeValues(): array
    {
        $values = config('youtrack.types.values', []);

        return is_array($values) ? array_values(array_filter($values)) : [];
    }

    public function connectionName(): string
    {
        return $this->connection;
    }

    protected function hasCredentials(): bool
    {
        $base = $this->connectionConfig('base_url') ?? config('youtrack.base_url');
        $token = $this->connectionConfig('token') ?? config('youtrack.token');

        return ! empty($base) && ! empty($token);
    }

    protected function token(): ?string
    {
        $value = $this->connectionConfig('token') ?? config('youtrack.token');

        return $value === null ? null : (string) $value;
    }

    /**
     * Read a per-connection config key. Returns null if the connection
     * isn't defined or the key is missing — callers fall back to the
     * top-level `youtrack.*` shim.
     */
    protected function connectionConfig(string $key): mixed
    {
        return config("youtrack.connections.{$this->connection}.{$key}");
    }
}
