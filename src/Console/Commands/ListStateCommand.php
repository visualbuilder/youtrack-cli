<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

/**
 * List issues in ANY exact workflow state, without needing a config key —
 * the escape hatch for board states the named list-* commands don't cover
 * (workflows evolve faster than CLI releases).
 */
class ListStateCommand extends ListByStateCommand
{
    protected $signature = 'youtrack:list-state
                            {state : Exact state name, e.g. "Approved - pending manual merge"}
                            {--project= : Project short name (e.g., NB)}
                            {--query= : Extra YQL appended after the Status filter}
                            {--page=1 : Page of results to return}
                            {--per-page=100 : Records per page, capped at 1000}';

    protected $description = 'List YouTrack issues in an arbitrary exact workflow state';

    protected function stateConfigKey(): string
    {
        return ''; // unused — resolveState() reads the argument instead
    }

    protected function resolveState(): string
    {
        return (string) $this->argument('state');
    }
}
