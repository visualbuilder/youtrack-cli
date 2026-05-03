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

/**
 * Verify a YouTrack project carries the fields the host expects. Both
 * buckets (required + recommended) come from `config('youtrack.fields.*')`,
 * so hosts customise without forking — neurohub adds `PR URL`,
 * `Error Count`, etc. via `YOUTRACK_RECOMMENDED_FIELDS`; other hosts list
 * whatever their workflow needs.
 */
#[Description('Verify a YouTrack project has the custom fields the host expects. Buckets results into required (default: stock Status/Priority/Type), recommended (host-configured), and extras (unknown to either list).')]
#[IsReadOnly]
class CheckProject extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $project = (string) ($request->get('project') ?: config('youtrack.default_project', 'NB'));

        $configured = $this->service($request)->getProjectFields($project)->all();

        $required = $this->fields('required');
        $recommended = $this->fields('recommended');

        $requiredMissing = array_values(array_diff($required, $configured));
        $recommendedMissing = array_values(array_diff($recommended, $configured));
        $extras = array_values(array_diff($configured, [...$required, ...$recommended]));

        return Response::json([
            'project' => $project,
            'ready' => $requiredMissing === [],
            'required' => [
                'configured' => array_values(array_intersect($required, $configured)),
                'missing' => $requiredMissing,
            ],
            'recommended' => [
                'configured' => array_values(array_intersect($recommended, $configured)),
                'missing' => $recommendedMissing,
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

    /**
     * @return array<int, string>
     */
    private function fields(string $bucket): array
    {
        $values = config("youtrack.fields.{$bucket}", []);

        return is_array($values) ? array_values(array_filter(array_map('strval', $values))) : [];
    }
}
