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

#[Description('Fetch a YouTrack issue including its custom fields and comments. Use when planning, code-review or QA needs the full ticket context.')]
#[IsReadOnly]
class GetIssue extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');

        if ($issueId === '') {
            return Response::error('issue_id is required (e.g., "NB-123").');
        }

        return Response::json(
            $this->service($request)->getIssue($issueId),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable, e.g. NB-123.')->required(),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
