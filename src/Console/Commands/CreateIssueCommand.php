<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class CreateIssueCommand extends BaseCommand
{
    protected $signature = 'youtrack:create-issue
                            {project : Project short name (e.g., NB)}
                            {summary : Issue summary/title}
                            {description : Issue description (supports markdown)}
                            {--type=Bug : Issue type (Bug, Enhancement, Feature, etc.)}
                            {--priority=P3 : Issue priority (P0 highest — P5 lowest)}';

    protected $description = 'Create a new YouTrack issue';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->createIssue(
                project: (string) $this->argument('project'),
                summary: (string) $this->argument('summary'),
                description: (string) $this->argument('description'),
                type: (string) $this->option('type'),
                priority: (string) $this->option('priority'),
            ),
        );

        return self::SUCCESS;
    }
}
