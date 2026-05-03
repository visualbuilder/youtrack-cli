<?php

declare(strict_types=1);

namespace Visualbuilder\YoutrackCli\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Visualbuilder\YoutrackCli\Mcp\Tools\Concerns\ResolvesIssueService;

#[Description('Set the value of any custom field on a YouTrack issue. The package detects whether the field is enum-shaped or simple and wraps the value accordingly. Numeric strings are auto-coerced to int.')]
class SetField extends Tool
{
    use ResolvesIssueService;

    public function handle(Request $request): Response
    {
        $issueId = (string) $request->get('issue_id', '');
        $field = (string) $request->get('field', '');
        $value = $request->get('value');

        if ($issueId === '' || $field === '' || $value === null) {
            return Response::error('issue_id, field, and value are all required.');
        }

        if (is_string($value) && is_numeric($value)) {
            $value = str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return Response::json(
            $this->service($request)->setCustomField($issueId, $field, $value),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->string()->description('Issue idReadable.')->required(),
            'field' => $schema->string()->description('Custom field name (e.g., "PR URL", "Error Count").')->required(),
            'value' => $schema->string()->description('Value to set. Strings, ints and floats all accepted.')->required(),
            'instance' => $schema->string()->description('Named YouTrack connection.'),
        ];
    }
}
