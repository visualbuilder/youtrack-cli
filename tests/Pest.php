<?php

declare(strict_types=1);

use Visualbuilder\YoutrackCli\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Decode the JSON payload an MCP tool emitted via Response::json(...).
 * Shared across every Mcp test file — Pest.php is the right home for
 * cross-file helpers.
 *
 * @return array<string, mixed>
 */
function decodeMcpResponse(\Laravel\Mcp\Response $response): array
{
    return json_decode((string) $response->content(), associative: true);
}
