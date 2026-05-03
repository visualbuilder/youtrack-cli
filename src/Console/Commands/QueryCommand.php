<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

/**
 * Raw YouTrack-query escape hatch — pass a complete YQL string and get
 * normalised issues back. Use when the dedicated `list-*` commands aren't
 * expressive enough (assignee, date filters, custom sort, cross-project
 * combinations, etc.).
 */
class QueryCommand extends BaseCommand
{
    protected $signature = 'youtrack:query
                            {query : Raw YouTrack YQL, e.g. "assignee: me #Unresolved sort by: updated desc"}
                            {--project= : Optional project short name to scope the query (e.g. NB)}
                            {--page=1 : Page of results to return (1-indexed)}
                            {--per-page=100 : Records per page, capped at 1000}';

    protected $description = 'Run a raw YouTrack query and return normalised issues with pagination metadata.';

    protected function youtrackHandle(): int
    {
        [$page, $perPage] = $this->paginationOptions();

        $project = $this->option('project') ?: null;
        $issues = $this->issueService()->query(
            yql: (string) $this->argument('query'),
            project: $project,
            page: $page,
            perPage: $perPage,
        );

        $items = $issues->map(fn (array $issue) => [
            'id' => $issue['id'],
            'summary' => $issue['summary'],
            'state' => $issue['state'],
            'priority' => $issue['priority'],
            'type' => $issue['type'],
            'created' => $issue['created'],
            'updated' => $issue['updated'],
        ])->values()->toArray();

        $this->emitJson([
            'count' => count($items),
            'project' => $project ?? config('youtrack.default_project'),
            'query' => (string) $this->argument('query'),
            'issues' => $items,
            ...$this->paginationEnvelope($items, $page, $perPage),
        ]);

        return self::SUCCESS;
    }
}
