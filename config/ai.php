<?php

declare(strict_types=1);

return [
    'default' => 'anthropic',

    'model' => env('AI_MODEL', 'claude-sonnet-5-5'),

    'timeout' => 30,

    'assistant_timeout' => 60,

    'csv_preview_max_rows' => 100,

    'categorize_batch' => 50,

    'throttle' => [
        'analyze' => 20,
    ],

    'confirm_channels' => [
        'issue_invoice' => ['chat', 'app'],
        'send_invoice' => ['chat', 'app'],
        'send_quote' => ['chat', 'app'],
        'sign_contract' => ['app'],
        'cancel_invoice' => ['app'],
        'delete_invoice_draft' => ['app'],
        'delete_quote' => ['app'],
        'delete_task' => ['app'],
        'delete_expense' => ['app'],
    ],

    'pending_action_ttl_minutes' => 10,

    'mcp_throttle' => 120,
];
