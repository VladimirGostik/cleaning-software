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

    'confirm_channels' => [],
];
