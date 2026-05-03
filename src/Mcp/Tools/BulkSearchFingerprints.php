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

#[Description('Search YouTrack for many error fingerprints in a single round trip. Used by the production-log monitor to dedupe error tickets before creating new ones.')]
#[IsReadOnly]
class BulkSearchFingerprints extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $fingerprints = $request->get('fingerprints');
        if (! is_array($fingerprints) || $fingerprints === []) {
            return Response::error('fingerprints must be a non-empty array of hash strings.');
        }

        $results = $this->service($request)->searchMultipleFingerprints(
            array_values(array_map('strval', $fingerprints)),
            $request->get('project'),
        );

        return Response::json([
            'count' => count(array_filter($results)),
            'project' => $request->get('project') ?? config('youtrack.default_project'),
            'results' => $results,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fingerprints' => $schema->array()->description('Array of fingerprint hash strings.')->required(),
            'project' => $schema->string()->description('Project shortname.'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
