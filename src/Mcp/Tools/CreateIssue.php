<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Create a new YouTrack issue. Type defaults to "Bug" and priority to "P3" — override either via the parameters.')]
class CreateIssue extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $project = (string) $request->get('project', '');
        $summary = (string) $request->get('summary', '');
        $description = (string) $request->get('description', '');

        if ($project === '' || $summary === '') {
            return Response::error('project and summary are required.');
        }

        return Response::json(
            $this->service($request)->createIssue(
                project: $project,
                summary: $summary,
                description: $description,
                type: (string) $request->get('type', 'Bug'),
                priority: (string) $request->get('priority', 'P3'),
            ),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project shortname (e.g., NB).')->required(),
            'summary' => $schema->string()->description('Issue summary / title.')->required(),
            'description' => $schema->string()->description('Issue description (markdown supported).'),
            'type' => $schema->string()->description('Issue type — Bug, Enhancement, Feature, etc. Defaults to Bug.'),
            'priority' => $schema->string()->description('Priority — P0–P5. Defaults to P3.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
