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

#[Description('List YouTrack issues currently in the "Plan Review" (blocked) state — tickets with open questions or human feedback awaiting agent action.')]
#[IsReadOnly]
class ListBlocked extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $project = $request->get('project');
        $issues = $this->service($request)->listBlocked($project)->values()->all();

        return Response::json([
            'count' => count($issues),
            'project' => $project ?? config('youtrack.default_project'),
            'state' => config('youtrack.states.blocked'),
            'issues' => $issues,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project shortname.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
