<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\YoutrackCli\Console\Commands\AddCommentCommand;
use Visualbuilder\YoutrackCli\Console\Commands\BulkSearchFingerprintsCommand;
use Visualbuilder\YoutrackCli\Console\Commands\CheckProjectCommand;
use Visualbuilder\YoutrackCli\Console\Commands\CreateIssueCommand;
use Visualbuilder\YoutrackCli\Console\Commands\GetIssueCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListApprovedCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListBlockedCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyForProductionCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyForStagingCommand;
use Visualbuilder\YoutrackCli\Console\Commands\SearchCommand;
use Visualbuilder\YoutrackCli\Console\Commands\SetFieldCommand;
use Visualbuilder\YoutrackCli\Console\Commands\UpdateStateCommand;

class YoutrackCliServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('youtrack-cli')
            ->hasConfigFile('youtrack')
            ->hasCommands([
                AddCommentCommand::class,
                BulkSearchFingerprintsCommand::class,
                CheckProjectCommand::class,
                CreateIssueCommand::class,
                GetIssueCommand::class,
                ListApprovedCommand::class,
                ListBlockedCommand::class,
                ListReadyCommand::class,
                ListReadyForProductionCommand::class,
                ListReadyForStagingCommand::class,
                SearchCommand::class,
                SetFieldCommand::class,
                UpdateStateCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // Ship the Claude Code skill (and any future ones in the package's
        // `.claude/` folder) as a publishable artifact. Hosts opt-in via
        // `php artisan vendor:publish --tag=youtrack-cli-claude-skills`,
        // which copies into the host project's `.claude/commands/`.
        $this->publishes([
            __DIR__ . '/../.claude/commands' => base_path('.claude/commands'),
        ], 'youtrack-cli-claude-skills');
    }
}
