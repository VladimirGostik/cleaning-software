<?php

declare(strict_types=1);

return [
    'key' => env('LEGACY_IMPORT_KEY'),
    'path' => database_path('seeders/data/legacy-import.enc'),
    'dry_run' => (bool) env('LEGACY_IMPORT_DRY_RUN', false),
];
