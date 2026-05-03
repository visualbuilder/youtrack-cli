<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Patch an issue\'s top-level summary and/or description. At least one of the two fields must be supplied.')]
class UpdateIssue extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        if ($issueId === '') {
            return Response::error('issue_id is required.');
        }

        $summary = $request->get('summary');
        $description = $request->get('description');
        if ($summary === null && $description === null) {
            return Response::error('Provide at least one of summary or description.');
        }

        return Response::json(
            $this->service($request)->updateIssue($issueId, $summary, $description),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable (e.g., NB-123).')->required(),
            'summary' => $schema->string()->description('New summary text.'),
            'description' => $schema->string()->description('New description text (markdown).'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
