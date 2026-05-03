<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Assign a YouTrack issue to a user (by login). Pass clear=true to unset the Assignee field instead.')]
class Assign extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        if ($issueId === '') {
            return Response::error('issue_id is required.');
        }

        $clear = (bool) $request->get('clear', false);
        $login = $clear ? null : ($request->get('assignee') ? (string) $request->get('assignee') : null);

        if (! $clear && $login === null) {
            return Response::error('Provide an assignee login, or pass clear=true to unset.');
        }

        return Response::json(
            $this->service($request)->assignIssue($issueId, $login),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'assignee' => $schema->string()->description('YouTrack login (omit when clear=true).'),
            'clear' => $schema->boolean()->description('Pass true to unset the Assignee field.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
