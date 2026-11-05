<?php

declare(strict_types=1);

$proxies = array_values(array_filter(
    array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))),
    fn (string $proxy): bool => $proxy !== '',
));

return [
    'proxies' => $proxies,

    'log_headers' => (bool) env('TRUSTED_PROXIES_LOG', false),
];
