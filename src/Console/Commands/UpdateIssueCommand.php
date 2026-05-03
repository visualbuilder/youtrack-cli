<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

/**
 * Patch an issue's top-level summary and/or description in one round trip.
 * At least one of the two flags must be supplied.
 */
class UpdateIssueCommand extends BaseCommand
{
    protected $signature = 'youtrack:update-issue
                            {issue_id : Issue ID (e.g., NB-123)}
                            {--summary= : New summary text}
                            {--description= : New description text (markdown)}';

    protected $description = 'Update a YouTrack issue\'s summary and/or description';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->updateIssue(
                issueId: (string) $this->argument('issue_id'),
                summary: $this->option('summary'),
                description: $this->option('description'),
            ),
        );

        return self::SUCCESS;
    }
}
