<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use InvalidArgumentException;
use Visualbuilder\YoutrackCli\Services\IssueService;

class BulkSearchFingerprintsCommand extends BaseCommand
{
    protected $signature = 'youtrack:bulk-search-fingerprints
                            {fingerprints : JSON array of fingerprint hashes}
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'Search YouTrack for multiple error fingerprints in a single API call';

    protected function youtrackHandle(): int
    {
        $raw = (string) $this->argument('fingerprints');
        $project = $this->option('project') ?: null;

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            // Throwing rather than emitting inline keeps the structured-error
            // envelope consistent with every other command — BaseCommand's
            // catch handles the framing.
            throw new InvalidArgumentException(
                'Invalid JSON array. Expected: \'["fp1","fp2",...]\'',
            );
        }

        $results = $this->issueService()->searchMultipleFingerprints($decoded, $project);

        $this->emitJson([
            'count' => count(array_filter($results)),
            'project' => $project ?? config('youtrack.default_project'),
            'results' => $results,
        ]);

        return self::SUCCESS;
    }
}
