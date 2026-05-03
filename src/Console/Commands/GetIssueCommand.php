<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class GetIssueCommand extends BaseCommand
{
    protected $signature = 'youtrack:get-issue
                            {issue : Issue ID (e.g., NB-123)}';

    protected $description = 'Get a YouTrack issue with full details including comments';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->getIssue((string) $this->argument('issue')),
        );

        return self::SUCCESS;
    }
}
