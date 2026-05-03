<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

class ListReadyForProductionCommand extends ListByStateCommand
{
    protected $signature = 'youtrack:list-ready-for-production
                            {--project= : Project short name (e.g., NB)}
                            {--query= : Extra YQL appended after the Status filter}
                            {--page=1 : Page of results to return}
                            {--per-page=100 : Records per page, capped at 1000}';

    protected $description = 'List YouTrack issues that are ready for production';

    protected function stateConfigKey(): string
    {
        return 'ready_for_production';
    }
}
