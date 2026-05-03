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

#[Description('Verify a YouTrack project has the custom fields the package relies on. Returns tier-1 (required), tier-2 (recommended) and unknown extra fields.')]
#[IsReadOnly]
class CheckProject extends Tool
{
    use ResolvesIssueService;

    private const TIER_1 = ['Status', 'Priority', 'Type'];

    private const TIER_2 = ['PR URL', 'Error Count', 'System Area', 'Requested By', 'Linked Initiative'];

    public function handle(Request $request): Response
    {
        $project = (string) ($request->get('project') ?: config('youtrack.default_project', 'NB'));

        $configured = $this->service($request)->getProjectFields($project)->all();

        $tier1Missing = array_values(array_diff(self::TIER_1, $configured));
        $tier2Missing = array_values(array_diff(self::TIER_2, $configured));
        $extras = array_values(array_diff($configured, [...self::TIER_1, ...self::TIER_2]));

        return Response::json([
            'project' => $project,
            'ready' => $tier1Missing === [],
            'tier_1' => [
                'configured' => array_values(array_intersect(self::TIER_1, $configured)),
                'missing' => $tier1Missing,
            ],
            'tier_2' => [
                'configured' => array_values(array_intersect(self::TIER_2, $configured)),
                'missing' => $tier2Missing,
            ],
            'extra_fields' => $extras,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project shortname (defaults to config default).'),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
