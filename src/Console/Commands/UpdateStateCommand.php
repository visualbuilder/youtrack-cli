<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;
use Illuminate\Console\Command;

class UpdateStateCommand extends Command
{
    protected $signature = 'youtrack:update-state
                            {issue_id : Issue ID (e.g., NB-123)}
                            {state : New state name (e.g., "Code Review")}';

    protected $description = 'Update the state of a YouTrack issue';

    public function handle(IssueService $issueService): int
    {
        $issueId = $this->argument('issue_id');
        $state = $this->argument('state');

        try {
            $result = $issueService->updateState($issueId, $state);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'issue_id' => $issueId,
                'state' => $state,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
