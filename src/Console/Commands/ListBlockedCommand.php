<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

class ListBlockedCommand extends ListByStateCommand
{
    protected $signature = 'youtrack:list-blocked
                            {--project= : Project short name (e.g., NB)}
                            {--query= : Extra YQL appended after the Status filter}
                            {--page=1 : Page of results to return}
                            {--per-page=100 : Records per page, capped at 1000}';

    protected $description = 'List YouTrack issues that are blocked (Plan Review state)';

    protected function stateConfigKey(): string
    {
        return 'blocked';
    }
}
