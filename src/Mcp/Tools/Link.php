<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Create an issue link in YouTrack ("depends on", "duplicates", "subtask of", etc.) using the natural-language commands API.')]
class Link extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $from = (string) $request->get('from', '');
        $to = (string) $request->get('to', '');

        if ($from === '' || $to === '') {
            return Response::error('from and to are both required (issue idReadable).');
        }

        return Response::json(
            $this->service($request)->linkIssues(
                $from,
                $to,
                (string) ($request->get('type') ?: 'depends on'),
            ),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Source issue (the one carrying the outgoing link).')->required(),
            'to' => $schema->string()->description('Target issue.')->required(),
            'type' => $schema->string()->description('Link type — "depends on", "duplicates", "subtask of", etc. Defaults to "depends on".'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
