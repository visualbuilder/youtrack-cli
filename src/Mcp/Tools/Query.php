<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Run a raw YouTrack YQL query — the escape hatch for assignee filters, date ranges, sort orders, anything outside the dedicated list/search tools. Returns paginated normalised issues.')]
#[IsReadOnly]
class Query extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $yql = (string) $request->get('query', '');
        if ($yql === '') {
            return Response::error('query is required (raw YQL string).');
        }

        $page = max(1, (int) ($request->get('page', 1) ?? 1));
        $perPage = max(1, min(1000, (int) ($request->get('per_page', 100) ?? 100)));

        $issues = $this->service($request)
            ->query($yql, $request->get('project'), $page, $perPage)
            ->values()
            ->all();

        return Response::json([
            'count' => count($issues),
            'query' => $yql,
            'page' => $page,
            'per_page' => $perPage,
            'next_page' => count($issues) >= $perPage ? $page + 1 : null,
            'issues' => $issues,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Raw YouTrack YQL, e.g. "assignee: me #Unresolved sort by: updated desc".')->required(),
            'project' => $schema->string()->description('Optional project shortname to scope the query.'),
            'page' => $schema->integer()->description('Page of results (1-indexed). Defaults to 1.'),
            'per_page' => $schema->integer()->description('Records per page. Capped at 1000. Defaults to 100.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
