<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Throwable;
use Visualbuilder\YoutrackCli\Services\IssueService;

class CheckProjectCommand extends BaseCommand
{
    protected $signature = 'youtrack:check-project
                            {--project= : Project short name (defaults to youtrack.default_project)}';

    protected $description = 'Verify a YouTrack project has the custom fields the CLI relies on. Splits results into tier 1 (required), tier 2 (recommended for dev-agent integration), and any extra org-specific fields.';

    /**
     * Tier 1 — required for any of the list/create/update commands to do
     * useful work. Missing any of these means the CLI cannot run end-to-end
     * against the project.
     *
     * @var array<int, string>
     */
    private const TIER_1_REQUIRED = ['Status', 'Priority', 'Type'];

    /**
     * Tier 2 — strongly recommended if the project is being driven by
     * dev-agent or the log monitor. Missing any of these means features
     * (PR linking, error dedup, routing) silently degrade.
     *
     * @var array<int, string>
     */
    private const TIER_2_RECOMMENDED = [
        'PR URL',
        'Error Count',
        'System Area',
        'Requested By',
        'Linked Initiative',
    ];

    protected function youtrackHandle(): int
    {
        $project = $this->resolveProject();
        $configured = $this->issueService()->getProjectFields($project)->all();

        $tier1Present = array_values(array_intersect(self::TIER_1_REQUIRED, $configured));
        $tier1Missing = array_values(array_diff(self::TIER_1_REQUIRED, $configured));
        $tier2Present = array_values(array_intersect(self::TIER_2_RECOMMENDED, $configured));
        $tier2Missing = array_values(array_diff(self::TIER_2_RECOMMENDED, $configured));

        $known = array_merge(self::TIER_1_REQUIRED, self::TIER_2_RECOMMENDED);
        $extras = array_values(array_diff($configured, $known));
        $ready = $tier1Missing === [];

        $this->emitJson([
            'project' => $project,
            'ready' => $ready,
            'tier_1' => [
                'configured' => $tier1Present,
                'missing' => $tier1Missing,
            ],
            'tier_2' => [
                'configured' => $tier2Present,
                'missing' => $tier2Missing,
            ],
            'extra_fields' => $extras,
            'all_fields' => $configured,
        ]);

        return $ready ? self::SUCCESS : self::FAILURE;
    }

    protected function errorPayload(Throwable $e): array
    {
        return parent::errorPayload($e) + [
            'project' => $this->resolveProject(),
        ];
    }

    private function resolveProject(): string
    {
        return (string) ($this->option('project') ?: config('youtrack.default_project', 'NB'));
    }
}
