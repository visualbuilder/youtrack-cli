<?php

declare(strict_types=1);

/*
 *     This file is part of neurohub.uk
 *     (c) Optima Cloud Technologies <lee@optimacloud.pro>
 *     @author Lee Evans
 *     @copyright 2023-2025 Optima Cloud Technologies
 *     This software is licensed to Neurobox for use in perpetuity, subject to agreement.
 */

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;
use Illuminate\Console\Command;

class BulkSearchFingerprintsCommand extends Command
{
    protected $signature = 'youtrack:bulk-search-fingerprints
                            {fingerprints : JSON array of fingerprint hashes}
                            {--project= : Project short name (e.g., NB)}';

    protected $description = 'Search YouTrack for multiple error fingerprints in a single API call';

    public function handle(IssueService $issueService): int
    {
        $raw = $this->argument('fingerprints');
        $project = $this->option('project');

        $fingerprints = json_decode($raw, true);

        if (! is_array($fingerprints)) {
            $this->error(json_encode([
                'error' => true,
                'message' => 'Invalid JSON array. Expected: \'["fp1","fp2",...]\'',
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }

        try {
            $results = $issueService->searchMultipleFingerprints($fingerprints, $project);

            $output = [
                'count' => count(array_filter($results)),
                'project' => $project ?? config('youtrack.default_project'),
                'results' => $results,
            ];

            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
