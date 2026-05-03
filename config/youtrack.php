<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | YouTrack workspaces
    |--------------------------------------------------------------------------
    |
    | One entry under `connections` per YouTrack workspace the host wants
    | to talk to. Single-instance hosts only need the `default` entry; any
    | additional workspaces (staging, support, etc.) get their own keys
    | and are picked at call-time via `--instance=NAME` on commands or
    | `(new YouTrackService())->on('staging')` programmatically.
    |
    */
    'default_connection' => env('YOUTRACK_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'base_url' => env('YOUTRACK_BASE_URL'),
            'token' => env('YOUTRACK_TOKEN'),
            'default_project' => env('YOUTRACK_DEFAULT_PROJECT', 'NB'),
        ],
        // 'support' => [
        //     'base_url' => env('YOUTRACK_SUPPORT_BASE_URL'),
        //     'token' => env('YOUTRACK_SUPPORT_TOKEN'),
        //     'default_project' => 'SUPP',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow states
    |--------------------------------------------------------------------------
    |
    | Always host-wide (not per connection) because the dev-agent's
    | state-machine vocabulary is consistent across every workspace it
    | operates against.
    |
    */
    'states' => [
        'ready' => env('YOUTRACK_READY_STATE', 'Ready for Dev'),
        'plan_review' => env('YOUTRACK_PLAN_REVIEW_STATE', 'Plan Review'),
        'in_progress' => env('YOUTRACK_IN_PROGRESS_STATE', 'In Progress'),
        'code_review' => env('YOUTRACK_CODE_REVIEW_STATE', 'Code Review'),
        'developer_approved' => env('YOUTRACK_DEVELOPER_APPROVED_STATE', 'Developer Approved'),
        'ready_for_qa' => env('YOUTRACK_READY_FOR_QA_STATE', 'Ready for QA'),
        'ready_for_staging' => env('YOUTRACK_READY_FOR_STAGING_STATE', 'Ready for Staging'),
        'ready_for_production' => env('YOUTRACK_READY_FOR_PRODUCTION_STATE', 'Ready for Production'),
        'blocked' => env('YOUTRACK_BLOCKED_STATE', 'Plan Review'),
        'done' => env('YOUTRACK_DONE_STATE', 'Done'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issue priorities
    |--------------------------------------------------------------------------
    |
    | YouTrack projects use different priority vocabularies — some use
    | P1–P5, some use Critical / Major / Normal / Minor / Trivial, others
    | use 1–10 numeric. Set whatever your project actually accepts here.
    |
    | `default` is what `youtrack:create-issue` and the CreateIssue MCP
    | tool use when --priority is not explicitly supplied. `values` is an
    | optional whitelist — when populated, the MCP tool's schema declares
    | it as an enum so AI agents see the valid options inline.
    |
    */
    'priorities' => [
        'default' => env('YOUTRACK_PRIORITY_DEFAULT', 'P3'),
        'values' => array_filter(array_map('trim', explode(
            ',',
            (string) env('YOUTRACK_PRIORITIES', 'P1,P2,P3,P4,P5'),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issue types
    |--------------------------------------------------------------------------
    |
    | Same shape as `priorities`. Override the env or replace the array if
    | your project doesn't use the stock YouTrack type list. Set `values`
    | to an empty array to disable the enum constraint.
    |
    */
    'types' => [
        'default' => env('YOUTRACK_TYPE_DEFAULT', 'Bug'),
        'values' => array_filter(array_map('trim', explode(
            ',',
            (string) env('YOUTRACK_TYPES', 'Bug,Feature,Task,Epic,Story'),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP behaviour
    |--------------------------------------------------------------------------
    */
    'http_timeout' => (int) env('YOUTRACK_HTTP_TIMEOUT', 30),
    'http_retries' => (int) env('YOUTRACK_HTTP_RETRIES', 2),
    'http_retry_delay' => (int) env('YOUTRACK_HTTP_RETRY_DELAY', 250),

    /*
    |--------------------------------------------------------------------------
    | Webhook receiver
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 secret the YouTrack webhook signs payloads with. Set the
    | same value here and in the YouTrack project's webhook config. Leave
    | unset to disable inbound webhooks (route returns 401 to every request).
    |
    */
    'webhook_secret' => env('YOUTRACK_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | MCP server
    |--------------------------------------------------------------------------
    |
    | When laravel/mcp is installed, the package registers a `youtrack` MCP
    | server with one tool per artisan command. Set false to disable.
    |
    */
    'mcp' => [
        'enabled' => env('YOUTRACK_MCP_ENABLED', true),
    ],
];
