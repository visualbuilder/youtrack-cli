<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

class AttachFileCommand extends BaseCommand
{
    protected $signature = 'youtrack:attach-file
                            {issue_id : Issue ID (e.g., NB-123)}
                            {path : Absolute or relative path to the file}
                            {--name= : Display name override (defaults to basename)}';

    protected $description = 'Attach a file (e.g. a screenshot) to a YouTrack issue.';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->attachFile(
                (string) $this->argument('issue_id'),
                (string) $this->argument('path'),
                $this->option('name') ?: null,
            ),
        );

        return self::SUCCESS;
    }
}
