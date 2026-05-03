<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Services;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Service for managing YouTrack issues.
 */
class IssueService
{
    public function __construct(
        protected YouTrackService $youTrack
    ) {}

    /**
     * Return an IssueService bound to a different named connection. Useful
     * when a single host operates against multiple YouTrack workspaces.
     */
    public function on(string $connection): static
    {
        return new static($this->youTrack->on($connection));
    }

    /**
     * List issues in a given state for a project.
     *
     * @param  string  $state  State name (e.g., "Ready for Dev")
     * @param  string|null  $project  Project short name (e.g., "NB")
     * @return Collection<int, array<string, mixed>>
     */
    public function listByState(string $state, ?string $project = null): Collection
    {
        $project = $project ?? $this->youTrack->defaultProject();

        $query = "project: {$project} Status: {$this->escapeQuery($state)}";

        $response = $this->youTrack->http()->get('issues', [
            'query' => $query,
            'fields' => 'id,idReadable,summary,description,created,updated,customFields(id,name,value(name))',
            '$top' => 100,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to list issues: {$response->status()} - {$response->body()}"
            );
        }

        return collect($response->json())->map(fn (array $issue) => $this->normalizeIssue($issue));
    }

    /**
     * List issues that are ready for development.
     *
     * @param  string|null  $project  Project short name
     * @return Collection<int, array<string, mixed>>
     */
    public function listReady(?string $project = null): Collection
    {
        return $this->listByState($this->youTrack->stateName('ready'), $project);
    }

    /**
     * List issues that are blocked (may have new feedback).
     *
     * @param  string|null  $project  Project short name
     * @return Collection<int, array<string, mixed>>
     */
    public function listBlocked(?string $project = null): Collection
    {
        return $this->listByState($this->youTrack->stateName('blocked'), $project);
    }

    /**
     * List issues that are developer approved (ready to merge to dev).
     */
    public function listApproved(?string $project = null): Collection
    {
        return $this->listByState($this->youTrack->stateName('developer_approved'), $project);
    }

    /**
     * List issues that are ready for staging.
     */
    public function listReadyForStaging(?string $project = null): Collection
    {
        return $this->listByState($this->youTrack->stateName('ready_for_staging'), $project);
    }

    /**
     * List issues that are ready for production.
     */
    public function listReadyForProduction(?string $project = null): Collection
    {
        return $this->listByState($this->youTrack->stateName('ready_for_production'), $project);
    }


    /**
     * Get a single issue with full details including comments.
     *
     * @param  string  $issueId  Issue ID (e.g., "NB-123")
     * @return array<string, mixed>
     */
    public function getIssue(string $issueId): array
    {
        $response = $this->youTrack->http()->get("issues/{$issueId}", [
            'fields' => 'id,idReadable,summary,description,created,updated,reporter(login,fullName),customFields(id,name,value(name))',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to get issue {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        $issue = $this->normalizeIssue($response->json());
        $issue['comments'] = $this->getComments($issueId)->toArray();

        return $issue;
    }

    /**
     * Get comments for an issue.
     *
     * @param  string  $issueId  Issue ID
     * @return Collection<int, array<string, mixed>>
     */
    public function getComments(string $issueId): Collection
    {
        $response = $this->youTrack->http()->get("issues/{$issueId}/comments", [
            'fields' => 'id,text,created,updated,author(login,fullName)',
            '$top' => 100,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to get comments for {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return collect($response->json())->map(fn (array $comment) => [
            'id' => $comment['id'] ?? null,
            'text' => $comment['text'] ?? '',
            'author' => $comment['author']['fullName'] ?? $comment['author']['login'] ?? 'Unknown',
            'created' => $this->formatTimestamp($comment['created'] ?? null),
            'updated' => $this->formatTimestamp($comment['updated'] ?? null),
        ]);
    }

    /**
     * Update the state of an issue.
     *
     * @param  string  $issueId  Issue ID
     * @param  string  $state  New state name
     * @return array<string, mixed>
     */
    public function updateState(string $issueId, string $state): array
    {
        // YouTrack accepts the state PATCH directly — no preflight GET needed.
        // The customField's $type is fixed (`StateIssueCustomField`); if the
        // project doesn't have a Status field the API will respond 4xx and we
        // surface that below.
        $response = $this->youTrack->http()->post("issues/{$issueId}", [
            'customFields' => [
                [
                    'name' => 'Status',
                    '$type' => 'StateIssueCustomField',
                    'value' => [
                        'name' => $state,
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to update state for {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'new_state' => $state,
            'success' => true,
        ];
    }

    /**
     * Patch an issue's top-level summary and/or description in one round
     * trip. Pass `null` to leave a field untouched.
     *
     * @return array<string, mixed>
     */
    public function updateIssue(string $issueId, ?string $summary = null, ?string $description = null): array
    {
        $payload = array_filter(
            ['summary' => $summary, 'description' => $description],
            static fn ($value): bool => $value !== null,
        );

        if ($payload === []) {
            throw new RuntimeException('updateIssue requires at least one of $summary or $description.');
        }

        $response = $this->youTrack->http()->post("issues/{$issueId}", $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to update issue {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'updated' => array_keys($payload),
            'success' => true,
        ];
    }

    /**
     * Assign an issue to a user, or pass `null` to clear the Assignee field.
     *
     * @return array<string, mixed>
     */
    public function assignIssue(string $issueId, ?string $assigneeLogin): array
    {
        $response = $this->youTrack->http()->post("issues/{$issueId}", [
            'customFields' => [
                [
                    'name' => 'Assignee',
                    '$type' => 'SingleUserIssueCustomField',
                    'value' => $assigneeLogin === null ? null : ['login' => $assigneeLogin],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to assign {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'assignee' => $assigneeLogin,
            'success' => true,
        ];
    }

    /**
     * Add a tag to an issue. YouTrack creates the tag if it doesn't exist
     * (subject to project permissions).
     *
     * @return array<string, mixed>
     */
    public function addTag(string $issueId, string $tag): array
    {
        $response = $this->youTrack->http()->post("issues/{$issueId}/tags", [
            'name' => $tag,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to add tag '{$tag}' to {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'tag' => $tag,
            'action' => 'added',
            'success' => true,
        ];
    }

    /**
     * Remove a tag from an issue. Resolves the tag's id by listing the
     * issue's tags first since YouTrack's DELETE endpoint requires it.
     *
     * @return array<string, mixed>
     */
    public function removeTag(string $issueId, string $tag): array
    {
        $listResponse = $this->youTrack->http()->get("issues/{$issueId}/tags", [
            'fields' => 'id,name',
        ]);

        if ($listResponse->failed()) {
            throw new RuntimeException(
                "Failed to read tags for {$issueId}: {$listResponse->status()} - {$listResponse->body()}"
            );
        }

        $tagId = collect($listResponse->json())
            ->firstWhere('name', $tag)['id'] ?? null;

        if ($tagId === null) {
            throw new RuntimeException("Tag '{$tag}' is not on issue {$issueId}.");
        }

        $deleteResponse = $this->youTrack->http()
            ->delete("issues/{$issueId}/tags/{$tagId}");

        if ($deleteResponse->failed()) {
            throw new RuntimeException(
                "Failed to remove tag '{$tag}' from {$issueId}: {$deleteResponse->status()} - {$deleteResponse->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'tag' => $tag,
            'action' => 'removed',
            'success' => true,
        ];
    }

    /**
     * Link two issues using YouTrack's `commands` endpoint, which accepts
     * the natural-language command syntax ("depends on NB-2", "duplicates
     * NB-3", etc.).
     *
     * @return array<string, mixed>
     */
    public function linkIssues(string $fromIssueId, string $toIssueId, string $linkTypeName = 'depends on'): array
    {
        $response = $this->youTrack->http()->post('commands', [
            'query' => "{$linkTypeName} {$toIssueId}",
            'issues' => [
                ['idReadable' => $fromIssueId],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to link {$fromIssueId} -> {$toIssueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'from' => $fromIssueId,
            'to' => $toIssueId,
            'link_type' => $linkTypeName,
            'success' => true,
        ];
    }

    /**
     * Set Resolution + Status in a single round trip — atomic, so the
     * issue can't be left in a half-resolved state if the second update
     * had failed.
     *
     * @return array<string, mixed>
     */
    public function resolveIssue(string $issueId, string $resolution, string $state): array
    {
        $response = $this->youTrack->http()->post("issues/{$issueId}", [
            'customFields' => [
                ['name' => 'Status', '$type' => 'StateIssueCustomField', 'value' => ['name' => $state]],
                ['name' => 'Resolution', '$type' => 'SingleEnumIssueCustomField', 'value' => ['name' => $resolution]],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to resolve {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'state' => $state,
            'resolution' => $resolution,
            'success' => true,
        ];
    }

    /**
     * Re-open a previously resolved issue: clear Resolution and move
     * Status back to the configured open state.
     *
     * @return array<string, mixed>
     */
    public function reopenIssue(string $issueId, string $state): array
    {
        $response = $this->youTrack->http()->post("issues/{$issueId}", [
            'customFields' => [
                ['name' => 'Status', '$type' => 'StateIssueCustomField', 'value' => ['name' => $state]],
                ['name' => 'Resolution', '$type' => 'SingleEnumIssueCustomField', 'value' => null],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to reopen {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'state' => $state,
            'success' => true,
        ];
    }

    /**
     * Add a comment to an issue.
     *
     * @param  string  $issueId  Issue ID
     * @param  string  $comment  Comment text (supports markdown)
     * @return array<string, mixed>
     */
    public function addComment(string $issueId, string $comment): array
    {
        $response = $this->youTrack->http()->post("issues/{$issueId}/comments", [
            'text' => $comment,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to add comment to {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        $data = $response->json();

        return [
            'issue_id' => $issueId,
            'comment_id' => $data['id'] ?? null,
            'success' => true,
        ];
    }

    /**
     * Set a custom field value on an issue.
     *
     * @param  string  $issueId  Issue ID
     * @param  string  $fieldName  Field name (e.g., "PR URL")
     * @param  string|int|float  $fieldValue  Field value
     * @return array<string, mixed>
     */
    public function setCustomField(string $issueId, string $fieldName, string|int|float $fieldValue): array
    {
        // Get the issue to find the field type
        $issue = $this->getIssueRaw($issueId);
        $field = $this->findCustomField($issue, $fieldName);

        if (! $field) {
            throw new RuntimeException("Cannot find custom field '{$fieldName}' for issue {$issueId}");
        }

        // Determine field type (default to SimpleIssueCustomField for text fields)
        $fieldType = $field['$type'] ?? 'SimpleIssueCustomField';

        // Enum fields need value wrapped as {'name': value}
        // Numeric SimpleIssueCustomField fields accept raw int/float
        $enumTypes = ['SingleEnumIssueCustomField', 'StateIssueCustomField'];
        if (in_array($fieldType, $enumTypes)) {
            $value = ['name' => $fieldValue];
        } elseif ($fieldType === 'SimpleIssueCustomField' && is_numeric($fieldValue)) {
            $value = (int) $fieldValue;
        } else {
            $value = $fieldValue;
        }

        $response = $this->youTrack->http()->post("issues/{$issueId}", [
            'customFields' => [
                [
                    'name' => $fieldName,
                    '$type' => $fieldType,
                    'value' => $value,
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to set field '{$fieldName}' for {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return [
            'issue_id' => $issueId,
            'field' => $fieldName,
            'value' => $fieldValue,
            'success' => true,
        ];
    }

    /**
     * Get raw issue data from API.
     *
     * @return array<string, mixed>
     */
    protected function getIssueRaw(string $issueId): array
    {
        $response = $this->youTrack->http()->get("issues/{$issueId}", [
            'fields' => 'id,idReadable,customFields(id,name,value(name),$type)',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to get issue {$issueId}: {$response->status()} - {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Find a custom field by name.
     *
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>|null
     */
    protected function findCustomField(array $issue, string $fieldName): ?array
    {
        $fields = $issue['customFields'] ?? [];

        foreach ($fields as $field) {
            if (($field['name'] ?? '') === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Normalize issue data to consistent format.
     *
     * @param  array<string, mixed>  $issue
     * @return array<string, mixed>
     */
    protected function normalizeIssue(array $issue): array
    {
        $customFields = [];
        foreach ($issue['customFields'] ?? [] as $field) {
            $name = $field['name'] ?? 'unknown';
            $value = $field['value']['name'] ?? $field['value'] ?? null;
            $customFields[$name] = $value;
        }

        return [
            'id' => $issue['idReadable'] ?? $issue['id'] ?? null,
            'internal_id' => $issue['id'] ?? null,
            'summary' => $issue['summary'] ?? '',
            'description' => $issue['description'] ?? '',
            'state' => $customFields['Status'] ?? null,
            'priority' => $customFields['Priority'] ?? null,
            'type' => $customFields['Type'] ?? null,
            'reporter' => $issue['reporter']['fullName'] ?? $issue['reporter']['login'] ?? null,
            'created' => $this->formatTimestamp($issue['created'] ?? null),
            'updated' => $this->formatTimestamp($issue['updated'] ?? null),
            'custom_fields' => $customFields,
        ];
    }

    /**
     * Format YouTrack timestamp to ISO format.
     */
    protected function formatTimestamp(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        // YouTrack returns timestamps in milliseconds. Use gmdate so the
        // emitted value is genuinely UTC regardless of the server's local
        // timezone — date() applies the local TZ then we'd be lying with
        // the trailing `Z`.
        return gmdate('Y-m-d\TH:i:s\Z', (int) ($timestamp / 1000));
    }

    /**
     * Run a raw YouTrack query (YQL) and return normalised issues.
     *
     * The escape hatch every consumer eventually wants — assignee filters,
     * date ranges, sort orders, complex boolean combinations. The `$project`
     * argument is a convenience: when present it's prepended as a
     * `project: {short}` clause; pass `null` (and a fully-qualified YQL) to
     * search across projects.
     *
     * Pagination is YouTrack-native via `$skip`/`$top`. The caller decides
     * whether there's more data by comparing the returned count against
     * `$perPage`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function query(
        string $yql,
        ?string $project = null,
        int $page = 1,
        int $perPage = 100,
    ): Collection {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));

        $project = $project ?? $this->youTrack->defaultProject();
        $fullQuery = trim($project ? "project: {$project} {$yql}" : $yql);

        $response = $this->youTrack->http()->get('issues', [
            'query' => $fullQuery,
            'fields' => 'id,idReadable,summary,description,created,updated,customFields(id,name,value(name))',
            '$skip' => ($page - 1) * $perPage,
            '$top' => $perPage,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to run YouTrack query: {$response->status()} - {$response->body()}"
            );
        }

        return collect($response->json())->map(fn (array $issue) => $this->normalizeIssue($issue));
    }

    /**
     * Search issues using a YouTrack query string.
     *
     * @param  string  $query  Search query (e.g., "error-fp:abc123")
     * @param  string|null  $project  Project short name (e.g., "NB")
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, ?string $project = null): Collection
    {
        $project = $project ?? $this->youTrack->defaultProject();

        // Hit both summary AND description so callers actually find issues
        // with the query word in the title (the most common case). YouTrack's
        // OR groups via the leading `#`-style — bare OR works inside a
        // project scope.
        $escaped = $this->escapeQuery($query);
        $fullQuery = "project: {$project} (summary: {$escaped} or description: {$escaped})";

        $response = $this->youTrack->http()->get('issues', [
            'query' => $fullQuery,
            'fields' => 'id,idReadable,summary,description,created,updated,customFields(id,name,value(name))',
            '$top' => 100,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to search issues: {$response->status()} - {$response->body()}"
            );
        }

        return collect($response->json())->map(fn (array $issue) => $this->normalizeIssue($issue));
    }

    /**
     * Search for multiple fingerprints in a single API call.
     *
     * Builds a single OR query to find all matching issues at once.
     * Returns results keyed by fingerprint.
     *
     * @param  array<int, string>  $fingerprints  List of fingerprint hashes
     * @param  string|null  $project  Project short name (e.g., "NB")
     * @return array<string, array{issue_id: string, state: string, error_count: int}|null>
     */
    public function searchMultipleFingerprints(array $fingerprints, ?string $project = null): array
    {
        $project = $project ?? $this->youTrack->defaultProject();

        if (empty($fingerprints)) {
            return [];
        }

        // Build OR query: (description: {error-fp:aaa} or description: {error-fp:bbb} or ...)
        $orClauses = array_map(
            fn (string $fp) => "description: {error-fp:{$fp}}",
            $fingerprints
        );
        $fullQuery = "project: {$project} (" . implode(' or ', $orClauses) . ')';

        $response = $this->youTrack->http()->get('issues', [
            'query' => $fullQuery,
            'fields' => 'id,idReadable,summary,description,created,updated,customFields(id,name,value(name),$type)',
            '$top' => 100,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to bulk search fingerprints: {$response->status()} - {$response->body()}"
            );
        }

        $issues = collect($response->json());

        // Map results back to fingerprints by extracting [error-fp:xxx] from descriptions
        $results = array_fill_keys($fingerprints, null);

        foreach ($issues as $issue) {
            $description = $issue['description'] ?? '';
            if (preg_match('/\[error-fp:([a-f0-9]+)\]/', $description, $matches)) {
                $fp = $matches[1];
                if (array_key_exists($fp, $results)) {
                    $normalized = $this->normalizeIssue($issue);
                    $errorCount = $this->extractNumericCustomField($issue, 'Error Count');
                    $results[$fp] = [
                        'issue_id' => $normalized['id'],
                        'state' => $normalized['state'],
                        'error_count' => $errorCount,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Extract a numeric custom field value from raw issue data.
     */
    protected function extractNumericCustomField(array $issue, string $fieldName): int
    {
        foreach ($issue['customFields'] ?? [] as $field) {
            if (($field['name'] ?? '') === $fieldName) {
                $value = $field['value'] ?? 0;
                return is_numeric($value) ? (int) $value : 0;
            }
        }

        return 0;
    }

    /**
     * Create a new issue in YouTrack.
     *
     * @param  string  $project  Project short name (e.g., "NB")
     * @param  string  $summary  Issue summary/title
     * @param  string  $description  Issue description (supports markdown)
     * @param  string  $type  Issue type (default: "Bug")
     * @param  string  $priority  Issue priority (default: "Normal")
     * @return array{issue_id: string|null, success: bool}
     */
    public function createIssue(
        string $project,
        string $summary,
        string $description,
        string $type = 'Bug',
        string $priority = 'Normal',
    ): array {
        $response = $this->youTrack->http()->post('issues?' . http_build_query([
            'fields' => 'id,idReadable,summary',
        ]), [
            'project' => ['shortName' => $project],
            'summary' => $summary,
            'description' => $description,
            'customFields' => [
                [
                    'name' => 'Type',
                    '$type' => 'SingleEnumIssueCustomField',
                    'value' => ['name' => $type],
                ],
                [
                    'name' => 'Priority',
                    '$type' => 'SingleEnumIssueCustomField',
                    'value' => ['name' => $priority],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to create issue: {$response->status()} - {$response->body()}"
            );
        }

        $data = $response->json();

        return [
            'issue_id' => $data['idReadable'] ?? null,
            'success' => true,
        ];
    }

    /**
     * List the custom field names configured on a YouTrack project.
     *
     * Used by the `youtrack:check-project` doctor command to verify a host's
     * project has the fields the CLI relies on. Hits the admin endpoint —
     * the configured token must have project-admin or sufficient read scope.
     *
     * @return Collection<int, string>
     */
    public function getProjectFields(?string $project = null): Collection
    {
        $project = $project ?? $this->youTrack->defaultProject();

        // Hit the project endpoint directly — `?query=` against
        // `admin/projects` is fragile when multiple projects share a prefix
        // (e.g. "NB" matching "NBV"). YouTrack accepts the shortName as the
        // {id} in this URL.
        $response = $this->youTrack->http()->get("admin/projects/{$project}", [
            'fields' => 'shortName,customFields(field(name))',
        ]);

        if ($response->status() === 404) {
            throw new RuntimeException("Project '{$project}' was not found in YouTrack.");
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to load project fields: {$response->status()} - {$response->body()}"
            );
        }

        $payload = $response->json();

        return collect($payload['customFields'] ?? [])
            ->map(static fn (array $entry): ?string => $entry['field']['name'] ?? null)
            ->filter()
            ->values();
    }

    /**
     * Escape special characters in YouTrack query.
     */
    protected function escapeQuery(string $value): string
    {
        // Wrap in braces for exact match
        return '{'.str_replace(['{', '}'], ['\\{', '\\}'], $value).'}';
    }
}
