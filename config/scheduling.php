<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Scheduling horizon
    |--------------------------------------------------------------------------
    |
    | Number of days ahead to generate recurring cleaning jobs for active
    | work breakdowns. Increasing this pre-generates more jobs; decreasing
    | it reduces pre-generation window. The daily command re-runs to keep
    | the window rolling.
    |
    */
    'horizon_days' => (int) env('SCHEDULING_HORIZON_DAYS', 30),
];
