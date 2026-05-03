<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Add a comment to a YouTrack issue. Markdown is supported.')]
class AddComment extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        $comment = (string) $request->get('comment', '');

        if ($issueId === '' || $comment === '') {
            return Response::error('issue_id and comment are both required.');
        }

        return Response::json(
            $this->service($request)->addComment($issueId, $comment),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'comment' => $schema->string()->description('Comment text. Supports markdown.')->required(),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
