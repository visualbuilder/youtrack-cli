<?php

declare(strict_types=1);

/*
 *     This file is part of neurohub.uk
 *     (c) Optima Cloud Technologies <lee@optimacloud.pro>
 *     @author Lee Evans
 *     @copyright 2023-2025 Optima Cloud Technologies
 *     This software is licensed to Neurobox for use in perpetuity, subject to agreement.
 */

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;
use Illuminate\Console\Command;

class ListReadyForProductionCommand extends Command
{
    protected $signature = 'youtrack:list-ready-for-production
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'List YouTrack issues that are ready for production';

    public function handle(IssueService $issueService): int
    {
        $project = $this->option('project');

        try {
            $issues = $issueService->listReadyForProduction($project);

            $output = [
                'count' => $issues->count(),
                'project' => $project ?? config('youtrack.default_project'),
                'state' => config('youtrack.states.ready_for_production'),
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
