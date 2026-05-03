<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class SetFieldCommand extends BaseCommand
{
    protected $signature = 'youtrack:set-field
                            {issue_id : Issue ID (e.g., NB-123)}
                            {field_name : Custom field name (e.g., "PR URL")}
                            {field_value : Value to set}';

    protected $description = 'Set a custom field value on a YouTrack issue';

    protected function youtrackHandle(): int
    {
        $issueId = (string) $this->argument('issue_id');
        $fieldName = (string) $this->argument('field_name');
        $fieldValue = $this->argument('field_value');

        // Auto-coerce numeric strings — YouTrack's numeric fields reject
        // string-typed values, and the CLI argument always arrives as string.
        if (is_string($fieldValue) && is_numeric($fieldValue)) {
            $fieldValue = str_contains($fieldValue, '.') ? (float) $fieldValue : (int) $fieldValue;
        }

        $this->emitJson(
            $this->issueService()->setCustomField($issueId, $fieldName, $fieldValue),
        );

        return self::SUCCESS;
    }
}
