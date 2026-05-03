<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class TagCommand extends BaseCommand
{
    protected $signature = 'youtrack:tag
                            {issue_id : Issue ID (e.g., NB-123)}
                            {tag : Tag name to add or remove}
                            {--remove : Remove the tag instead of adding it}';

    protected $description = 'Add or remove a tag on a YouTrack issue';

    protected function youtrackHandle(): int
    {
        $service = $this->issueService();
        $issueId = (string) $this->argument('issue_id');
        $tag = (string) $this->argument('tag');

        $result = $this->option('remove')
            ? $service->removeTag($issueId, $tag)
            : $service->addTag($issueId, $tag);

        $this->emitJson($result);

        return self::SUCCESS;
    }
}
