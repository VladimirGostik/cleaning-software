<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Thrown from a tool once a task run is out of time: laravel/ai has no stop condition, and an exception from a tool is the only way to end its step loop. */
final class TurnBudgetExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The task run used its time budget.');
    }
}
