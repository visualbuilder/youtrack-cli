<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Resolve a YouTrack issue — sets Status + Resolution atomically in a single round trip. Resolution defaults to "Fixed".')]
class Resolve extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        if ($issueId === '') {
            return Response::error('issue_id is required.');
        }

        $resolution = (string) ($request->get('as') ?: 'Fixed');
        $state = (string) ($request->get('state') ?: config('youtrack.states.done', 'Done'));

        return Response::json(
            $this->service($request)->resolveIssue($issueId, $resolution, $state),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'as' => $schema->string()->description('Resolution name (Fixed, Duplicate, Won\'t fix, …). Defaults to Fixed.'),
            'state' => $schema->string()->description('Override the target state. Defaults to config youtrack.states.done.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
