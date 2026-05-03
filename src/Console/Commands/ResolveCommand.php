<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class ResolveCommand extends BaseCommand
{
    protected $signature = 'youtrack:resolve
                            {issue_id : Issue ID (e.g., NB-123)}
                            {--as=Fixed : Resolution name (Fixed, Duplicate, Won\'t fix, etc.)}
                            {--state= : Override the target state (defaults to youtrack.states.done)}';

    protected $description = 'Resolve a YouTrack issue — sets Status + Resolution in a single atomic update';

    protected function youtrackHandle(): int
    {
        $state = (string) ($this->option('state') ?: config('youtrack.states.done', 'Done'));

        $this->emitJson(
            $this->issueService()->resolveIssue(
                issueId: (string) $this->argument('issue_id'),
                resolution: (string) $this->option('as'),
                state: $state,
            ),
        );

        return self::SUCCESS;
    }
}
