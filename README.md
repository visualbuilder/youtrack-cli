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
