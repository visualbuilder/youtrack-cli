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

class SetFieldCommand extends Command
{
    protected $signature = 'youtrack:set-field
                            {issue_id : Issue ID (e.g., NB-123)}
                            {field_name : Custom field name (e.g., "PR URL")}
                            {field_value : Value to set}';

    protected $description = 'Set a custom field value on a YouTrack issue';

    public function handle(IssueService $issueService): int
    {
        $issueId = $this->argument('issue_id');
        $fieldName = $this->argument('field_name');
        $fieldValue = $this->argument('field_value');

        // Auto-detect numeric values from string arguments
        if (is_numeric($fieldValue)) {
            $fieldValue = str_contains($fieldValue, '.') ? (float) $fieldValue : (int) $fieldValue;
        }

        try {
            $result = $issueService->setCustomField($issueId, $fieldName, $fieldValue);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(json_encode([
                'error' => true,
                'issue_id' => $issueId,
                'field' => $fieldName,
                'message' => $e->getMessage(),
            ], JSON_PRETTY_PRINT));

            return Command::FAILURE;
        }
    }
}
