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

#[Description('Search issues by free text. Matches both summary and description.')]
#[IsReadOnly]
class Search extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query', '');
        if ($query === '') {
            return Response::error('query is required.');
        }

        $issues = $this->service($request)->search($query, $request->get('project'))
            ->values()
            ->all();

        return Response::json([
            'count' => count($issues),
            'query' => $query,
            'issues' => $issues,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free-text fragment to search.')->required(),
            'project' => $schema->string()->description('Project shortname.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
