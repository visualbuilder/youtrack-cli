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

class GetIssueCommand extends Command
{
    protected $signature = 'youtrack:get-issue
                            {issue : Issue ID (e.g., NB-123)}';

    protected $description = 'Get a YouTrack issue with full details including comments';

    public function handle(IssueService $issueService): int
    {
        $issueId = $this->argument('issue');

        try {
            $issue = $issueService->getIssue($issueId);

            $this->line(json_encode($issue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'issue_id' => $issueId,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
