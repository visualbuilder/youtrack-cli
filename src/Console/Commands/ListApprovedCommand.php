<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;
use Illuminate\Console\Command;

class ListApprovedCommand extends Command
{
    protected $signature = 'youtrack:list-approved
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'List YouTrack issues that are developer approved (ready to merge to dev)';

    public function handle(IssueService $issueService): int
    {
        $project = $this->option('project');

        try {
            $issues = $issueService->listApproved($project);

            $output = [
                'count' => $issues->count(),
                'project' => $project ?? config('youtrack.default_project'),
                'state' => config('youtrack.states.developer_approved'),
                'issues' => $issues->map(fn (array $issue) => [
                    'id' => $issue['id'],
                    'summary' => $issue['summary'],
                    'priority' => $issue['priority'],
                    'type' => $issue['type'],
                    'updated' => $issue['updated'],
                    'custom_fields' => $issue['custom_fields'],
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
