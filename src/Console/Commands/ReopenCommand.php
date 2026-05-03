<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class ReopenCommand extends BaseCommand
{
    protected $signature = 'youtrack:reopen
                            {issue_id : Issue ID (e.g., NB-123)}
                            {--state= : Target state (defaults to youtrack.states.ready)}';

    protected $description = 'Re-open a resolved YouTrack issue — clears Resolution and moves Status to the configured open state';

    protected function youtrackHandle(): int
    {
        $state = (string) ($this->option('state') ?: config('youtrack.states.ready', 'Ready for Dev'));

        $this->emitJson(
            $this->issueService()->reopenIssue(
                issueId: (string) $this->argument('issue_id'),
                state: $state,
            ),
        );

        return self::SUCCESS;
    }
}
