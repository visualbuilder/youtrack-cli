<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class UpdateStateCommand extends BaseCommand
{
    protected $signature = 'youtrack:update-state
                            {issue_id : Issue ID (e.g., NB-123)}
                            {state : New state name (e.g., "Code Review")}';

    protected $description = 'Update the state of a YouTrack issue';

    protected function youtrackHandle(): int
    {
        $issueId = (string) $this->argument('issue_id');
        $state = (string) $this->argument('state');

        $this->emitJson(
            $this->issueService()->updateState($issueId, $state),
        );

        return self::SUCCESS;
    }
}
