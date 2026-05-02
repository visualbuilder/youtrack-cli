# YouTrack CLI

Laravel artisan commands for driving YouTrack issue workflows from the terminal, scripts, and AI agents.

Twelve commands, JSON output, env-driven config. Designed to be the source of truth that any consumer (a coding agent, a CI pipeline, a developer's terminal, a slash command) can call.

## Install

```bash
composer require visualbuilder/youtrack-cli
```

The service provider auto-registers; no manual wiring needed. Optionally publish the config to override workflow state names:

```bash
php artisan vendor:publish --tag=youtrack-cli-config
```

## Configure

Set in your host `.env`:

```dotenv
YOUTRACK_BASE_URL=https://your-org.youtrack.cloud
YOUTRACK_TOKEN=perm:your-permanent-token
YOUTRACK_DEFAULT_PROJECT=NB
```

Workflow-state names default to a sensible 10-step lifecycle (Ready for Dev → In Progress → Code Review → … → Done) and are env-overridable via `config/youtrack.php`.

## YouTrack project setup

The CLI reads YouTrack custom fields by exact name. Fields fall into three tiers — only tier 1 is mandatory for the basic commands to work.

### Tier 1 — required

The CLI cannot do useful work without these. They are stock YouTrack defaults — every project ships with them.

| What we call it | YouTrack custom field | YouTrack type | Used by |
|---|---|---|---|
| **state** | `Status` | state (single) | every list-by-state command, `update-state`, normalised `state` key |
| **priority** | `Priority` | enum (single) | `create-issue --priority=P3`, normalised `priority` key |
| **type** | `Type` | enum (single) | `create-issue --type=Bug`, normalised `type` key |

YouTrack's `state` type is workflow-aware — its values are the kanban columns of the project. Tier 1 uses YouTrack's stock field set, so a fresh project ships ready out of the box.

> "state" vs "Status" — we picked **state** as the public-surface name (it's the standard term in workflow engines), but on the wire the package reads/writes YouTrack's `Status` custom field. They mean the same thing: the column a ticket sits in on your kanban board.

### Tier 2 — recommended for `dev-agent` / log-monitor integration

Strongly recommended if you're driving the project with `dev-agent` or the production log monitor. Missing any of these makes features silently degrade rather than fail.

| Field | YouTrack type | What uses it |
|---|---|---|
| `PR URL` | string | dev-agent saves the GitHub PR URL after opening it: `youtrack:set-field NB-X "PR URL" "https://..."` |
| `Error Count` | integer | log monitor stores how many production-error occurrences a fingerprint has seen |
| `System Area` | enum (single) | optional routing — which subsystem the issue concerns |
| `Requested By` | string | optional — who in the company asked for it |
| `Linked Initiative` | enum (single) | optional — links a ticket to a higher-level OKR / project |

Add these in **Project Settings → Fields** in the YouTrack UI. The CLI doesn't care about types beyond what each command sends, so plain strings work where you don't need an enum.

### Tier 3 — anything else

The package doesn't hard-code field names beyond tier 1. Custom fields specific to your org work transparently:

```bash
# Read every custom field on a ticket — `custom_fields` in the JSON output
# carries the full untouched map.
php artisan youtrack:get-issue NB-123

# Write any field by exact name — works for stock + custom.
php artisan youtrack:set-field NB-123 "QA Approval" "Verified by Hugo"
```

### Verifying your setup

Run the doctor command after configuring the project — it lists every custom field and splits them into tiers, returning exit code `0` when tier 1 is complete and `1` when something required is missing.

```bash
php artisan youtrack:check-project --project=NB
```

```json
{
    "project": "NB",
    "ready": true,
    "tier_1": {
        "configured": ["Status", "Priority", "Type"],
        "missing": []
    },
    "tier_2": {
        "configured": ["PR URL"],
        "missing": ["Error Count", "System Area", "Requested By", "Linked Initiative"]
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
