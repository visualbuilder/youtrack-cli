<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class AssignCommand extends BaseCommand
{
    protected $signature = 'youtrack:assign
                            {issue_id : Issue ID (e.g., NB-123)}
                            {assignee? : YouTrack login (omit when using --clear)}
                            {--clear : Unset the Assignee field}';

    protected $description = 'Assign a YouTrack issue to a user, or clear the Assignee field with --clear';

    protected function youtrackHandle(): int
    {
        $clear = (bool) $this->option('clear');
        $login = $clear ? null : (string) $this->argument('assignee');

        if (! $clear && $login === '') {
            // Without --clear, an explicit login is required. Forces callers
            // to be unambiguous instead of silently unassigning when they
            // forget the argument.
            throw new \InvalidArgumentException(
                'Pass an assignee login, or --clear to unset the Assignee field.',
            );
        }

        $this->emitJson(
            $this->issueService()->assignIssue(
                issueId: (string) $this->argument('issue_id'),
                assigneeLogin: $login,
            ),
        );

        return self::SUCCESS;
    }
}
