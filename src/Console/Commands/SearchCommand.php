<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class SearchCommand extends BaseCommand
{
    protected $signature = 'youtrack:search
                            {query : Search query text}
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'Search YouTrack issues across summary and description';

    protected function youtrackHandle(): int
    {
        $query = (string) $this->argument('query');
        $project = $this->option('project') ?: null;

        $issues = $this->issueService()->search($query, $project);

        $items = $issues->map(fn (array $issue) => [
            'id' => $issue['id'],
            'summary' => $issue['summary'],
            'state' => $issue['state'],
            'priority' => $issue['priority'],
            'type' => $issue['type'],
            'created' => $issue['created'],
        ])->values()->toArray();

        $this->emitJson([
            'count' => count($items),
            'project' => $project ?? config('youtrack.default_project'),
            'query' => $query,
            'issues' => $items,
        ]);

        return self::SUCCESS;
    }
}
