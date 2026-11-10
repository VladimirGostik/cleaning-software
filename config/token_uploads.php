<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('TOKEN_UPLOADS_ENABLED', false),

    'throttle' => 10,
];
