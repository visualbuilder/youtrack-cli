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

class CreateIssueCommand extends Command
{
    protected $signature = 'youtrack:create-issue
                            {project : Project short name (e.g., NB)}
                            {summary : Issue summary/title}
                            {description : Issue description (supports markdown)}
                            {--type=Bug : Issue type (Bug, Enhancement, Feature, etc.)}
                            {--priority=P3 : Issue priority (P0 highest — P5 lowest)}';

    protected $description = 'Create a new YouTrack issue';

    public function handle(IssueService $issueService): int
    {
        $project = $this->argument('project');
        $summary = $this->argument('summary');
        $description = $this->argument('description');
        $type = $this->option('type');
        $priority = $this->option('priority');

        try {
            $result = $issueService->createIssue($project, $summary, $description, $type, $priority);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'project' => $project,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
