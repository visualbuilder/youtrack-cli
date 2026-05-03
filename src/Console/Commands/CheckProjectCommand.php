<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Throwable;

/**
 * Verify a YouTrack project carries the custom fields the host relies on.
 *
 * Two buckets, both driven by `config('youtrack.fields.*')`:
 *
 *   required    — must be present, otherwise `ready` is false and the
 *                 command exits non-zero. Defaults to YouTrack's stock
 *                 trio (Status / Priority / Type) — universal across
 *                 every project.
 *   recommended — host-specific fields the package benefits from but
 *                 can degrade without. Empty by default; hosts populate
 *                 via env (`YOUTRACK_RECOMMENDED_FIELDS=PR URL,Error Count`)
 *                 or by publishing the config.
 *
 * Anything in the project that's outside both buckets surfaces as
 * `extra_fields` so hosts can spot drift.
 */
class CheckProjectCommand extends BaseCommand
{
    protected $signature = 'youtrack:check-project
                            {--project= : Project short name (defaults to youtrack.default_project)}';

    protected $description = 'Verify a YouTrack project has the custom fields the host relies on. Buckets results into required / recommended / extras.';

    protected function youtrackHandle(): int
    {
        $project = $this->resolveProject();
        $configured = $this->issueService()->getProjectFields($project)->all();

        $required = $this->configuredFields('required');
        $recommended = $this->configuredFields('recommended');

        $requiredPresent = array_values(array_intersect($required, $configured));
        $requiredMissing = array_values(array_diff($required, $configured));
        $recommendedPresent = array_values(array_intersect($recommended, $configured));
        $recommendedMissing = array_values(array_diff($recommended, $configured));

        $known = array_merge($required, $recommended);
        $extras = array_values(array_diff($configured, $known));
        $ready = $requiredMissing === [];

        $this->emitJson([
            'project' => $project,
            'ready' => $ready,
            'required' => [
                'configured' => $requiredPresent,
                'missing' => $requiredMissing,
            ],
            'recommended' => [
                'configured' => $recommendedPresent,
                'missing' => $recommendedMissing,
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

    /**
     * @return array<int, string>
     */
    private function configuredFields(string $bucket): array
    {
        $values = config("youtrack.fields.{$bucket}", []);

        return is_array($values) ? array_values(array_filter(array_map('strval', $values))) : [];
    }
}
