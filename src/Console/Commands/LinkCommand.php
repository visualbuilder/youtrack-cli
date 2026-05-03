<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class LinkCommand extends BaseCommand
{
    protected $signature = 'youtrack:link
                            {from : Issue that gets the outgoing link (e.g., NB-1)}
                            {to : Issue the link points to (e.g., NB-2)}
                            {--type=depends on : Link type — "depends on", "duplicates", "subtask of", etc.}';

    protected $description = 'Create an issue link in YouTrack via the natural-language commands API';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->linkIssues(
                fromIssueId: (string) $this->argument('from'),
                toIssueId: (string) $this->argument('to'),
                linkTypeName: (string) $this->option('type'),
            ),
        );

        return self::SUCCESS;
    }
}
