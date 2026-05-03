<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Create a new YouTrack issue. Type and priority default to whatever the host has configured under youtrack.types.default and youtrack.priorities.default — pass the parameters explicitly to override.')]
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

        $type = (string) ($request->get('type') ?: config('youtrack.types.default', 'Bug'));
        $priority = (string) ($request->get('priority') ?: config('youtrack.priorities.default', 'P3'));

        return Response::json(
            $this->service($request)->createIssue(
                project: $project,
                summary: $summary,
                description: $description,
                type: $type,
                priority: $priority,
            ),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        // Surface the configured priority/type whitelists as JSON-schema enums
        // so AI agents see the exact values this host's YouTrack accepts —
        // no more guessing "Major" when the project uses P-grades.
        $typeValues = (array) config('youtrack.types.values', []);
        $priorityValues = (array) config('youtrack.priorities.values', []);

        $typeField = $schema->string()->description(
            'Issue type. Defaults to ' . config('youtrack.types.default', 'Bug') . '.',
        );
        if ($typeValues !== []) {
            $typeField = $typeField->enum($typeValues);
        }

        $priorityField = $schema->string()->description(
            'Priority. Defaults to ' . config('youtrack.priorities.default', 'P3') . '.',
        );
        if ($priorityValues !== []) {
            $priorityField = $priorityField->enum($priorityValues);
        }

        return [
            'project' => $schema->string()->description('Project shortname (e.g., NB).')->required(),
            'summary' => $schema->string()->description('Issue summary / title.')->required(),
            'description' => $schema->string()->description('Issue description (markdown supported).'),
            'type' => $typeField,
            'priority' => $priorityField,
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
