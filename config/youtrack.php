<?php

declare(strict_types=1);

/*
 *     This file is part of neurohub.uk
 *     (c) Optima Cloud Technologies <lee@optimacloud.pro>
 *     @author Lee Evans
 *     @copyright 2023-2025 Optima Cloud Technologies
 *     This software is licensed to Neurobox for use in perpetuity, subject to agreement.
 */

return [
    'base_url' => env('YOUTRACK_BASE_URL'),
    'token' => env('YOUTRACK_TOKEN'),
    'default_project' => env('YOUTRACK_DEFAULT_PROJECT', 'NB'),

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

    'http_timeout' => (int) env('YOUTRACK_HTTP_TIMEOUT', 30),
    'http_retries' => (int) env('YOUTRACK_HTTP_RETRIES', 2),
    'http_retry_delay' => (int) env('YOUTRACK_HTTP_RETRY_DELAY', 250),
];
