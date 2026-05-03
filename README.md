# YouTrack CLI

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/youtrack-cli.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/youtrack-cli)
![Packagist Downloads](https://img.shields.io/packagist/dt/visualbuilder/youtrack-cli)
[![run-tests](https://github.com/visualbuilder/youtrack-cli/actions/workflows/run-tests.yml/badge.svg)](https://github.com/visualbuilder/youtrack-cli/actions/workflows/run-tests.yml)
![GitHub last commit](https://img.shields.io/github/last-commit/visualbuilder/youtrack-cli)

Laravel artisan commands, MCP tools, and a webhook receiver for driving YouTrack issue workflows from terminals, scripts, and AI agents.

20 commands, 20 MCP tools, structured-error JSON envelopes, multi-instance config, signed-webhook receiver. Designed to be the source of truth any consumer (coding agent, CI pipeline, developer's terminal, MCP-aware IDE, slash command) can call.

## Install

```bash
composer require visualbuilder/youtrack-cli
```

The service provider auto-registers; no manual wiring needed. Optionally publish the config to override workflow state names:

```bash
php artisan vendor:publish --tag=youtrack-cli-config
```

If you drive YouTrack with Claude Code, install the bundled slash-command skill into your host's `.claude/commands/`:

```bash
php artisan vendor:publish --tag=youtrack-cli-claude-skills
```

`/youtrack` then gives Claude the full command surface, lifecycle walkthrough, and project-setup pointers as context.

## Configure

Set in your host `.env`:

```dotenv
YOUTRACK_BASE_URL=https://your-org.youtrack.cloud
YOUTRACK_TOKEN=perm:your-permanent-token
YOUTRACK_DEFAULT_PROJECT=NB
```

Workflow-state names default to a sensible 10-step lifecycle (Ready for Dev → In Progress → Code Review → … → Done) and are env-overridable via `config/youtrack.php`.

### Custom priority and type vocabularies

Hosts that don't use the stock `Bug / Feature / Task` types or `P1–P5` priority grades configure their own under `youtrack.priorities` and `youtrack.types`:

```dotenv
# Comma-separated lists. Default values used when --priority / --type are omitted.
YOUTRACK_PRIORITY_DEFAULT=Major
YOUTRACK_PRIORITIES=Critical,Major,Normal,Minor,Trivial
YOUTRACK_TYPE_DEFAULT=Defect
YOUTRACK_TYPES=Defect,Story,Spike,Epic
```

The `values` arrays drive the `CreateIssue` MCP tool's JSON-schema enums, so AI agents see exactly which values your YouTrack project accepts. Leave a list empty to disable the constraint and fall back to "anything goes".

### Multi-workspace

Talking to more than one YouTrack workspace? Add another entry under `youtrack.connections`:

```php
// config/youtrack.php
'connections' => [
    'default' => [
        'base_url' => env('YOUTRACK_BASE_URL'),
        'token' => env('YOUTRACK_TOKEN'),
        'default_project' => 'NB',
    ],
    'support' => [
        'base_url' => env('YOUTRACK_SUPPORT_BASE_URL'),
        'token' => env('YOUTRACK_SUPPORT_TOKEN'),
        'default_project' => 'SUPP',
    ],
],
```

Every artisan command accepts `--instance=NAME`; programmatic callers use `(new YouTrackService())->on('support')` or `app(IssueService::class)->on('support')`. Single-workspace hosts only need the `default` entry.

### MCP server (optional)

When `laravel/mcp` is installed, the package registers a `youtrack` MCP server exposing every artisan command as an agent-callable tool. Disable via `YOUTRACK_MCP_ENABLED=false` if you want CLI-only.

### Webhook receiver

Inbound YouTrack webhooks land at `POST /youtrack/webhook`. Configure the same secret in your YouTrack project's webhook settings and in `.env`:

```dotenv
YOUTRACK_WEBHOOK_SECRET=base64-string-shared-with-youtrack
```

Subscribe to `Visualbuilder\YoutrackCli\Events\YoutrackWebhookReceived` from the host's `EventServiceProvider` to react to deliveries. The package itself ships zero default behaviour — what to do with an event is a host concern. Idempotent on `X-YouTrack-Delivery-Id` for 24h.

## YouTrack project setup

The CLI reads YouTrack custom fields by exact name. Two configurable buckets:

### Required fields (stock YouTrack defaults)

The CLI cannot do useful work without these. They're shipped with every YouTrack project — nothing to configure unless your project has been customised.

| What we call it | YouTrack custom field | YouTrack type | Used by |
|---|---|---|---|
| **state** | `Status` | state (single) | every list-by-state command, `update-state`, normalised `state` key |
| **priority** | `Priority` | enum (single) | `create-issue --priority=P3`, normalised `priority` key |
| **type** | `Type` | enum (single) | `create-issue --type=Bug`, normalised `type` key |

> "state" vs "Status" — we picked **state** as the public-surface name (it's the standard term in workflow engines), but on the wire the package reads/writes YouTrack's `Status` custom field. They mean the same thing: the column a ticket sits in on your kanban board.

If your project diverges (some YouTrack tenants enforce additional required fields), extend the list:

```dotenv
YOUTRACK_REQUIRED_FIELDS=Status,Priority,Type,Severity
```

### Recommended fields (host-specific)

Empty by default — populate with whatever fields your workflow expects beyond the stock trio. Examples from the agentic ecosystem this package was extracted from:

```dotenv
YOUTRACK_RECOMMENDED_FIELDS=PR URL,Error Count,System Area,Requested By
```

| Example field | YouTrack type | What it'd be for |
|---|---|---|
| `PR URL` | string | a `dev-agent` saving the GitHub PR URL after opening it |
| `Error Count` | integer | a log monitor storing occurrence counts per error fingerprint |
| `System Area` | enum (single) | routing tickets to subsystems |

Add these in **Project Settings → Fields** in the YouTrack UI. They're optional — `youtrack:check-project` reports them as `recommended.missing` rather than failing.

### Anything else

Custom fields not in either list still work transparently — `get-issue` returns the full untouched `custom_fields` map, and `set-field` writes any field by exact name:

```bash
php artisan youtrack:set-field NB-123 "QA Approval" "Verified by Hugo"
```

### Verifying your setup

Run the doctor command after configuring the project — it returns exit code `0` when every required field is present and `1` when something's missing.

```bash
php artisan youtrack:check-project --project=NB
```

```json
{
    "project": "NB",
    "ready": true,
    "required": {
        "configured": ["Status", "Priority", "Type"],
        "missing": []
    },
    "recommended": {
        "configured": ["PR URL"],
        "missing": ["Error Count"]
    },
    "extra_fields": ["Custom Org Field"],
    "all_fields": ["Status", "Priority", "Type", "PR URL", "Custom Org Field"]
}
```

### Recommended state vocabulary

The package's default state names match a ten-step development lifecycle. If your YouTrack project's `Status` enum values are different, override them in `config/youtrack.php` (or via env in your service provider) — every list/update command resolves through that map, no hard-coded strings in command bodies.

| Config key | Default value |
|---|---|
| `youtrack.states.ready` | Ready for Dev |
| `youtrack.states.plan_review` | Plan Review |
| `youtrack.states.in_progress` | In Progress |
| `youtrack.states.code_review` | Code Review |
| `youtrack.states.developer_approved` | Developer Approved |
| `youtrack.states.ready_for_qa` | Ready for QA |
| `youtrack.states.ready_for_staging` | Ready for Staging |
| `youtrack.states.staging_review` | Staging Review |
| `youtrack.states.ready_for_production` | Ready for Production |
| `youtrack.states.done` | Done |

Adding new states is a config-only change; the CLI never hard-codes a value beyond what `config('youtrack.states.*')` returns.

## Commands

Every command writes a JSON document to stdout — friendly for `jq`, AI agents, and shell pipelines.

### Listing tickets by state

```bash
php artisan youtrack:list-ready --project=NB
php artisan youtrack:list-blocked --project=NB
php artisan youtrack:list-approved --project=NB
php artisan youtrack:list-ready-for-staging --project=NB
php artisan youtrack:list-ready-for-production --project=NB
```

```json
{
    "count": 3,
    "project": "NB",
    "state": "Ready for Dev",
    "issues": [
        { "id": "NB-123", "summary": "...", "priority": "P3", "type": "Bug" }
    ]
}
```

### Inspecting one ticket

```bash
php artisan youtrack:get-issue NB-123
```

Returns full details: summary, description, state, priority, type, custom fields (PR URL, assignee, etc.), and every comment.

### Searching

```bash
php artisan youtrack:search "session reminder" --project=NB
```

Free-text search over summaries and descriptions.

### Creating a ticket

```bash
php artisan youtrack:create-issue NB \
    "Mobile dashboard wraps awkwardly under 375px" \
    "## Steps to reproduce
    
    1. ..." \
    --type=Bug \
    --priority=P3
```

Defaults: `--type=Bug`, `--priority=P3` (valid: P0 highest — P5 lowest, types: Bug, Enhancement, Feature, …).

### Moving workflow state

```bash
php artisan youtrack:update-state NB-123 "In Progress"
php artisan youtrack:update-state NB-123 "Code Review"
php artisan youtrack:update-state NB-123 "Ready for QA"
```

Pass the human-readable state name exactly as it appears on your board.

### Custom fields

```bash
php artisan youtrack:set-field NB-123 "PR URL" "https://github.com/org/repo/pull/42"
```

Common one — saves a PR link as a custom field on the ticket.

### Comments

```bash
php artisan youtrack:add-comment NB-123 "Deployed to staging at $(date -u)."
```

Markdown supported.

### Bulk fingerprint search

```bash
php artisan youtrack:bulk-search-fingerprints '["abc123","def456","ghi789"]' --project=NB
```

Hits multiple error-fingerprint hashes in a single API call — used by error-monitoring pipelines to dedupe before opening new tickets.

### Checking project setup

```bash
php artisan youtrack:check-project --project=NB
```

Audits the project's custom fields against tier 1 (required) and tier 2 (recommended). Exit code `0` when ready, `1` when a tier-1 field is missing or the project doesn't exist. See *YouTrack project setup* below.

## Programmatic use

Inject the service classes:

```php
use Visualbuilder\YoutrackCli\Services\IssueService;

class DispatchTickets
{
    public function __construct(private IssueService $issues) {}

    public function handle(): void
    {
        $this->issues->createIssue(
            project: 'NB',
            summary: 'Performance regression on /reports',
            description: 'Median page load 1.8s → 4.2s after merge of NB-980.',
            type: 'Bug',
            priority: 'P1',
        );
    }
}
```

`IssueService` wraps every command's logic; `YouTrackService` is the underlying HTTP client.

## License

GPL-2.0-or-later.
