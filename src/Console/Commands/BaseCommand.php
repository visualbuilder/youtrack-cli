<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use Visualbuilder\YoutrackCli\Services\IssueService;

/**
 * Shared scaffolding for every `youtrack:*` artisan command.
 *
 * Concrete commands implement `run(): int` instead of `handle(): int`. This
 * base wraps the call in a try/catch that produces a uniform JSON envelope
 * on failure — `{ok: false, error: "...", status: int}` — so agents that
 * pipe artisan output into a JSON parser get predictable error data
 * instead of stack traces.
 *
 * Successful runs use `$this->emitJson($payload)` to print a formatted JSON
 * blob; commands that need pagination flags pull them via `paginationOptions()`.
 */
abstract class BaseCommand extends Command
{
    /**
     * Symfony lifecycle hook — adds the host-wide `--instance` option to
     * every concrete command without each subclass having to advertise it
     * in its own signature.
     */
    protected function configure(): void
    {
        parent::configure();

        if (! $this->getDefinition()->hasOption('instance')) {
            $this->addOption(
                name: 'instance',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Named YouTrack connection from config(youtrack.connections.*). Defaults to the configured `default_connection`.',
            );
        }
    }

    final public function handle(): int
    {
        try {
            return $this->youtrackHandle();
        } catch (Throwable $e) {
            $this->line(json_encode(
                $this->errorPayload($e),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::FAILURE;
        }
    }

    abstract protected function youtrackHandle(): int;

    /**
     * Default structured-error envelope. Subclasses can override to add
     * command-specific context (e.g. `project`, `issue_id`) — typically
     * by returning `parent::errorPayload($e) + ['extra' => ...]`.
     *
     * @return array<string, mixed>
     */
    protected function errorPayload(Throwable $e): array
    {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'status' => $e instanceof RuntimeException ? 502 : 500,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function emitJson(array $payload): void
    {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Resolve `--page` / `--per-page` flags into a sane page/perPage tuple.
     * Defaults: page 1, perPage 100. Cap perPage at 1000 to protect the API.
     *
     * @return array{0: int, 1: int}
     */
    protected function paginationOptions(): array
    {
        $page = max(1, (int) ($this->option('page') ?? 1));
        $perPage = max(1, min(1000, (int) ($this->option('per-page') ?? 100)));

        return [$page, $perPage];
    }

    /**
     * Resolve the IssueService scoped to the connection requested via
     * `--instance`. Every BaseCommand auto-inherits the option through
     * the configure() hook above, so each concrete command can rely on
     * the flag being present and just call `$this->issueService()`.
     */
    protected function issueService(): IssueService
    {
        $instance = (string) ($this->option('instance') ?? '');

        $service = app(IssueService::class);

        return $instance !== '' ? $service->on($instance) : $service;
    }

    /**
     * Build the standard pagination envelope. `next_page` is null when the
     * underlying API returned fewer records than `$perPage`, signalling the
     * caller has reached the end of the result set.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    protected function paginationEnvelope(array $items, int $page, int $perPage): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total_returned' => count($items),
            'next_page' => count($items) >= $perPage ? $page + 1 : null,
        ];
    }
}
