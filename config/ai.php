<?php

declare(strict_types=1);

return [
    'default' => 'anthropic',

    'model' => env('AI_MODEL', 'claude-sonnet-5-5'),

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            // Without this laravel/ai forces tool_choice for structured output, which the current models reject with a 400.
            'anthropic_beta' => 'structured-outputs-2025-11-13',
        ],
    ],

    'timeout' => 30,

    'assistant_timeout' => 18,

    'categorize_batch' => 50,

    'throttle' => [
        'analyze' => 20,
        'assistant' => 30,
    ],

    'timezone' => 'Europe/Bratislava',

    'assistant' => [
        'max_steps' => 6,
        'turn_seconds' => 20,
        'max_tool_output_chars' => 20000,
        'max_messages' => 20,
        'max_message_chars' => 4000,
        'max_total_chars' => 12000,

        'attachments' => [
            'types' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'application/pdf' => 'pdf',
            ],
            'max_files' => 5,
            'image_max_kb' => 5120,
            'pdf_max_kb' => 10240,
            'total_max_kb' => 12288,
            'pdf_max_pages' => 10,
            'total_max_pages' => 20,
            'max_writes' => 5,
            'max_name_chars' => 255,
            'page_count_timeout_seconds' => 10,
        ],
    ],

    'guard' => [
        'mcp_restriction_minutes' => 15,
        'max_writes_per_turn' => 10,
        'max_prepares_per_turn' => 5,
    ],

    'task_run' => [
        'queue' => env('AI_TASK_RUN_QUEUE', 'ai'),
        'turn_seconds' => 45,
        'step_seconds' => 25,
        'pending_action_ttl_minutes' => 1440,
        'stale_minutes' => 15,
        'running_stale_seconds' => 120,
        'context_comments' => 30,
        'context_chars' => 12000,
    ],

    'task_parse_max_chars' => 2000,

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
