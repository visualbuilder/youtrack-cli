<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

/**
 * Shared logic for every `youtrack:list-*` command. Each concrete subclass
 * supplies a state config key (`ready`, `blocked`, etc.) — the run loop
 * resolves it to a state name, builds the YQL, applies any caller-supplied
 * `--query` clauses, paginates, and emits a uniform envelope.
 *
 * Keeps the per-state subclasses tiny (signature + description + key).
 */
abstract class ListByStateCommand extends BaseCommand
{
    /**
     * The key under `config('youtrack.states.*')` whose value names the
     * state this command lists.
     */
    abstract protected function stateConfigKey(): string;

    protected function youtrackHandle(): int
    {
        [$page, $perPage] = $this->paginationOptions();

        $project = $this->option('project') ?: null;
        $extraQuery = trim((string) $this->option('query'));
        $state = (string) config('youtrack.states.' . $this->stateConfigKey());

        $yql = 'Status: {' . $state . '}';
        if ($extraQuery !== '') {
            $yql .= ' ' . $extraQuery;
        }

        $issues = $this->issueService()->query($yql, $project, $page, $perPage);

        $items = $issues->map(fn (array $issue) => [
            'id' => $issue['id'],
            'summary' => $issue['summary'],
            'priority' => $issue['priority'],
            'type' => $issue['type'],
            'created' => $issue['created'],
            'updated' => $issue['updated'],
        ])->values()->toArray();

        $this->emitJson([
            'count' => count($items),
            'project' => $project ?? config('youtrack.default_project'),
            'state' => $state,
            'issues' => $items,
            ...$this->paginationEnvelope($items, $page, $perPage),
        ]);

        return self::SUCCESS;
    }
}
