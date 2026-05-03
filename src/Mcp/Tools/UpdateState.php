<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Move a YouTrack issue to a new Status. State name is taken verbatim — must match a state defined on the project (e.g., "Code Review", "Ready for QA").')]
class UpdateState extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        $state = (string) $request->get('state', '');

        if ($issueId === '' || $state === '') {
            return Response::error('issue_id and state are both required.');
        }

        return Response::json(
            $this->service($request)->updateState($issueId, $state),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'state' => $schema->string()->description('Target state name.')->required(),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
