<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class CreateIssueCommand extends BaseCommand
{
    /**
     * --type and --priority default to empty strings so `option()` returns
     * a falsy value when the caller didn't pass the flag — `youtrackHandle`
     * then resolves the host-configured defaults from `youtrack.types` /
     * `youtrack.priorities`. Hardcoding `--type=Bug --priority=P3` here
     * would lock the package to neurohub's vocabulary for everyone.
     */
    protected $signature = 'youtrack:create-issue
                            {project : Project short name (e.g., NB)}
                            {summary : Issue summary/title}
                            {description : Issue description (supports markdown)}
                            {--type= : Issue type (defaults to youtrack.types.default)}
                            {--priority= : Issue priority (defaults to youtrack.priorities.default)}';

    protected $description = 'Create a new YouTrack issue';

    protected function youtrackHandle(): int
    {
        $type = (string) ($this->option('type') ?: config('youtrack.types.default', 'Bug'));
        $priority = (string) ($this->option('priority') ?: config('youtrack.priorities.default', 'P3'));

        $this->emitJson(
            $this->issueService()->createIssue(
                project: (string) $this->argument('project'),
                summary: (string) $this->argument('summary'),
                description: (string) $this->argument('description'),
                type: $type,
                priority: $priority,
            ),
        );

        return self::SUCCESS;
    }
}
