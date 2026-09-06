<?php

declare(strict_types=1);

return [
    'alerts' => [
        'per_type_limit' => 15,
        'total_limit' => 50,
    ],

    // Window length (days, inclusive of today) for the "unassigned jobs" alert + metric.
    'unassigned_horizon_days' => 7,
];
