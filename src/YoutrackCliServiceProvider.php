<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\YoutrackCli\Console\Commands\AddCommentCommand;
use Visualbuilder\YoutrackCli\Console\Commands\AssignCommand;
use Visualbuilder\YoutrackCli\Console\Commands\BulkSearchFingerprintsCommand;
use Visualbuilder\YoutrackCli\Console\Commands\CheckProjectCommand;
use Visualbuilder\YoutrackCli\Console\Commands\CreateIssueCommand;
use Visualbuilder\YoutrackCli\Console\Commands\GetIssueCommand;
use Visualbuilder\YoutrackCli\Console\Commands\LinkCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListApprovedCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListBlockedCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyForProductionCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ListReadyForStagingCommand;
use Visualbuilder\YoutrackCli\Console\Commands\QueryCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ReopenCommand;
use Visualbuilder\YoutrackCli\Console\Commands\ResolveCommand;
use Visualbuilder\YoutrackCli\Console\Commands\SearchCommand;
use Visualbuilder\YoutrackCli\Console\Commands\SetFieldCommand;
use Visualbuilder\YoutrackCli\Console\Commands\TagCommand;
use Visualbuilder\YoutrackCli\Console\Commands\UpdateIssueCommand;
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
                AssignCommand::class,
                BulkSearchFingerprintsCommand::class,
                CheckProjectCommand::class,
                CreateIssueCommand::class,
                GetIssueCommand::class,
                LinkCommand::class,
                ListApprovedCommand::class,
                ListBlockedCommand::class,
                ListReadyCommand::class,
                ListReadyForProductionCommand::class,
                ListReadyForStagingCommand::class,
                QueryCommand::class,
                ReopenCommand::class,
                ResolveCommand::class,
                SearchCommand::class,
                SetFieldCommand::class,
                TagCommand::class,
                UpdateIssueCommand::class,
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

        $this->registerMcpServer();
        $this->registerWebhookRoute();
    }

    /**
     * Register the YouTrack MCP server when laravel/mcp is available and
     * the host hasn't disabled it. Mirrors the pattern used in
     * visualbuilder/filament-design-system.
     */
    protected function registerMcpServer(): void
    {
        if (! class_exists(\Laravel\Mcp\Facades\Mcp::class)) {
            return;
        }

        if (! (bool) config('youtrack.mcp.enabled', true)) {
            return;
        }

        \Laravel\Mcp\Facades\Mcp::local('youtrack', \Visualbuilder\YoutrackCli\Mcp\YoutrackServer::class);
    }

    /**
     * Register the inbound `youtrack/webhook` route. Always loaded —
     * hosts that don't configure `youtrack.webhook_secret` get a 401 back
     * for every request, so leaving the route registered is harmless.
     */
    protected function registerWebhookRoute(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
