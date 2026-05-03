<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Add or remove a tag on a YouTrack issue. Pass remove=true to remove instead of add.')]
class Tag extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        $tag = (string) $request->get('tag', '');

        if ($issueId === '' || $tag === '') {
            return Response::error('issue_id and tag are both required.');
        }

        $service = $this->service($request);
        $result = $request->get('remove')
            ? $service->removeTag($issueId, $tag)
            : $service->addTag($issueId, $tag);

        return Response::json($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'tag' => $schema->string()->description('Tag name.')->required(),
            'remove' => $schema->boolean()->description('Pass true to remove the tag instead of adding it.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
