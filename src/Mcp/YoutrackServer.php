<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Visualbuilder\YoutrackCli\Mcp\Tools\AddComment;
use Visualbuilder\YoutrackCli\Mcp\Tools\Assign;
use Visualbuilder\YoutrackCli\Mcp\Tools\BulkSearchFingerprints;
use Visualbuilder\YoutrackCli\Mcp\Tools\CheckProject;
use Visualbuilder\YoutrackCli\Mcp\Tools\CreateIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\GetIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\Link;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListApproved;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListBlocked;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReady;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReadyForProduction;
use Visualbuilder\YoutrackCli\Mcp\Tools\ListReadyForStaging;
use Visualbuilder\YoutrackCli\Mcp\Tools\Query;
use Visualbuilder\YoutrackCli\Mcp\Tools\Reopen;
use Visualbuilder\YoutrackCli\Mcp\Tools\Resolve;
use Visualbuilder\YoutrackCli\Mcp\Tools\Search;
use Visualbuilder\YoutrackCli\Mcp\Tools\SetField;
use Visualbuilder\YoutrackCli\Mcp\Tools\Tag;
use Visualbuilder\YoutrackCli\Mcp\Tools\UpdateIssue;
use Visualbuilder\YoutrackCli\Mcp\Tools\UpdateState;

#[Name('YouTrack CLI')]
#[Version('1.0.0')]
#[Instructions(<<<'EOT'
    This MCP server exposes neurohub's YouTrack workflow CLI as agent-callable
    tools. Every Artisan command from `youtrack:*` has a sibling tool here
    backed by the same IssueService — no shelling required.

    Read tools (idempotent, safe to call freely):
      list_ready, list_blocked, list_approved, list_ready_for_staging,
      list_ready_for_production — list issues in well-known dev-agent states.
      get_issue — full ticket details + comments.
      query — raw YouTrack YQL escape hatch with pagination.
      search — summary+description text search.
      bulk_search_fingerprints — error-monitor dedup helper.
      check_project — verify required custom fields on a project.

    Write tools (mutate YouTrack state — be deliberate):
      create_issue, update_issue, add_comment, set_field
      update_state, resolve, reopen
      assign, tag (or tag with remove=true), link

    Multi-instance: every tool accepts an optional `instance` param naming a
    connection from `config('youtrack.connections.*')`. Omit to use the
    configured default workspace.
    EOT)]
class YoutrackServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Reads
        ListReady::class,
        ListBlocked::class,
        ListApproved::class,
        ListReadyForStaging::class,
        ListReadyForProduction::class,
        GetIssue::class,
        Query::class,
        Search::class,
        BulkSearchFingerprints::class,
        CheckProject::class,
        // Writes
        CreateIssue::class,
        UpdateIssue::class,
        AddComment::class,
        SetField::class,
        UpdateState::class,
        Resolve::class,
        Reopen::class,
        Assign::class,
        Tag::class,
        Link::class,
    ];
}
