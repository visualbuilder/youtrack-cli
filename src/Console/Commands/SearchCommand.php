<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;
use Illuminate\Console\Command;

class SearchCommand extends Command
{
    protected $signature = 'youtrack:search
                            {query : Search query text}
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'Search YouTrack issues by query text';

    public function handle(IssueService $issueService): int
    {
        $query = $this->argument('query');
        $project = $this->option('project');

        try {
            $issues = $issueService->search($query, $project);

            $output = [
                'count' => $issues->count(),
                'project' => $project ?? config('youtrack.default_project'),
                'query' => $query,
                'issues' => $issues->map(fn (array $issue) => [
                    'id' => $issue['id'],
                    'summary' => $issue['summary'],
                    'state' => $issue['state'],
                    'priority' => $issue['priority'],
                    'type' => $issue['type'],
                    'created' => $issue['created'],
                ])->values()->toArray(),
            ];

            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
