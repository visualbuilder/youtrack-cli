<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Console\Commands;

use Visualbuilder\YoutrackCli\Services\IssueService;

class AddCommentCommand extends BaseCommand
{
    protected $signature = 'youtrack:add-comment
                            {issue_id : Issue ID (e.g., NB-123)}
                            {comment : Comment text (supports markdown)}';

    protected $description = 'Add a comment to a YouTrack issue';

    protected function youtrackHandle(): int
    {
        $this->emitJson(
            $this->issueService()->addComment(
                (string) $this->argument('issue_id'),
                (string) $this->argument('comment'),
            ),
        );

        return self::SUCCESS;
    }
}
