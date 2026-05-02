# YouTrack CLI

Laravel artisan commands for YouTrack workflow automation. Extracted from `neurohub` so `dev-agent` and any consumer Laravel project share one source of truth.

## Why

`dev-agent` (Python) shells out to `php artisan youtrack:*` to drive ticket lifecycles. Hosting these commands inside a single product (`neurohub`) couples dev-agent to that codebase and forces every other project to reinvent the wheel. This package lifts them out:

- Same artisan command signatures (`youtrack:list-ready`, `youtrack:create-issue`, `youtrack:update-state`, …)
- Same env-driven config (`YOUTRACK_BASE_URL`, `YOUTRACK_TOKEN`, `YOUTRACK_DEFAULT_PROJECT`)
- Same `IssueService` / `YouTrackService` for direct PHP use
- Drop-in `composer require` for any Laravel app

## Install

```bash
composer require visualbuilder/youtrack-cli
```

The service provider is auto-registered. Optionally publish the config to override the workflow state names:

```bash
php artisan vendor:publish --tag=youtrack-cli-config
```

## Configuration

Set in your host `.env`:

```dotenv
YOUTRACK_BASE_URL=https://your-org.youtrack.cloud
YOUTRACK_TOKEN=perm:your-permanent-token
YOUTRACK_DEFAULT_PROJECT=NB
```

State names (defaults match dev-agent's lifecycle) are env-overridable — see `config/youtrack.php`.

## Commands

All commands return JSON for AI/automation friendliness.

### Listing by state

| Command | State |
|---|---|
| `youtrack:list-ready --project=NB` | Ready for Dev |
| `youtrack:list-blocked --project=NB` | Plan Review |
| `youtrack:list-approved --project=NB` | Developer Approved |
| `youtrack:list-ready-for-staging --project=NB` | Ready for Staging |
| `youtrack:list-ready-for-production --project=NB` | Ready for Production |

### Issue management

| Command | What it does |
|---|---|
| `youtrack:get-issue NB-123` | Full ticket details + comments |
| `youtrack:search "query" --project=NB` | Free-text search |
| `youtrack:create-issue NB "Title" "Body" --type=Bug --priority=P3` | New ticket |
| `youtrack:update-state NB-123 "In Progress"` | Move workflow state |
| `youtrack:set-field NB-123 "PR URL" "https://…"` | Set custom field |
| `youtrack:add-comment NB-123 "Comment **markdown**"` | Append comment |
| `youtrack:bulk-search-fingerprints '["hash1","hash2"]' --project=NB` | Dedupe lookup for log monitor |

### Defaults

- `--type` defaults to `Bug` (valid: `Bug`, `Enhancement`, `Feature`, …)
- `--priority` defaults to `P3` (valid: `P0` highest — `P5` lowest)

## Programmatic use

```php
use Visualbuilder\YoutrackCli\Services\IssueService;

$service = app(IssueService::class);
$result = $service->createIssue('NB', 'Title', 'Body', type: 'Bug', priority: 'P3');
```

## License

GPL-2.0-or-later.
