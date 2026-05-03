<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Re-open a previously resolved YouTrack issue — clears Resolution and moves Status to the configured open state.')]
class Reopen extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        if ($issueId === '') {
            return Response::error('issue_id is required.');
        }

        $state = (string) ($request->get('state') ?: config('youtrack.states.ready', 'Ready for Dev'));

        return Response::json(
            $this->service($request)->reopenIssue($issueId, $state),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'state' => $schema->string()->description('Override target state. Defaults to config youtrack.states.ready.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
