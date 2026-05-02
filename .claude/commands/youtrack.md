# YouTrack Commands

Drive YouTrack issue workflows from the host's terminal. Every command emits JSON for clean piping into `jq`, agent prompts, or shell scripts. The package's project default comes from `config/youtrack.php` (env: `YOUTRACK_DEFAULT_PROJECT`); pass `--project=ABC` on any command to override.

## Listing tickets by state

```bash
php artisan youtrack:list-ready                # Ready for Dev
php artisan youtrack:list-blocked              # Plan Review (questions / feedback waiting on a human)
php artisan youtrack:list-approved             # Developer Approved (ready to merge to dev)
php artisan youtrack:list-ready-for-staging    # QA approved, ready for staging deploy
php artisan youtrack:list-ready-for-production # Verified on staging, ready for production batch
```

```json
{"count": 2, "project": "NB", "state": "Ready for Dev", "issues": [{"id": "NB-123", "summary": "...", "priority": "P3", "type": "Bug"}]}
```

## Inspecting one ticket

```bash
php artisan youtrack:get-issue NB-123
```

Full details including summary, description, state, priority, type, custom fields, and every comment.

## Searching

```bash
php artisan youtrack:search "session reminder"
```

Free-text search across summaries and descriptions.

## Creating

```bash
php artisan youtrack:create-issue NB \
  "Mobile dashboard wraps under 375px" \
  "## Steps to reproduce\n1. ..." \
  --type=Bug \
  --priority=P3
```

- `--type` (default `Bug`) — `Bug`, `Enhancement`, `Feature`, etc.
- `--priority` (default `P3`) — `P0` highest through `P5` lowest.

## Moving workflow state

```bash
php artisan youtrack:update-state NB-123 "In Progress"
php artisan youtrack:update-state NB-123 "Code Review"
php artisan youtrack:update-state NB-123 "Ready for QA"
```

State names must match the project's `Status` field exactly. The package's `config/youtrack.php` ships sensible defaults (Ready for Dev → Done) and is env-overridable.

## Custom fields and comments

```bash
php artisan youtrack:set-field NB-123 "PR URL" "https://github.com/org/repo/pull/42"
php artisan youtrack:add-comment NB-123 "Deployed to staging at $(date -u)."
```

Markdown is supported in comments.

## Verifying project setup

```bash
php artisan youtrack:check-project --project=NB
```

Audits the project's custom fields against tier 1 (required: `Status`, `Priority`, `Type`) and tier 2 (recommended for dev-agent / log monitor: `PR URL`, `Error Count`, `System Area`, `Requested By`, `Linked Initiative`). Exit code `0` when ready, `1` when a tier-1 field is missing or the project isn't found.

## Typical lifecycle

1. `youtrack:list-ready` to find work.
2. `youtrack:update-state NB-123 "In Progress"` to claim it.
3. Implement, push branch, open PR.
4. `youtrack:set-field NB-123 "PR URL" "..."` to wire the PR onto the ticket.
5. `youtrack:add-comment NB-123 "..."` with implementation notes.
6. `youtrack:update-state NB-123 "Code Review"` for review.
7. After merge to dev: `youtrack:update-state NB-123 "Ready for QA"`.
8. `Ready for Staging` → `Ready for Production` → `Done` as it ships.

If something blocks progress: `youtrack:update-state NB-123 "Plan Review"` and `youtrack:add-comment` with the question.
