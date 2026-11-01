<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskRunOutcomeEnum: string
{
    case Done = 'done';
    case Question = 'question';
    case Cannot = 'cannot';
}
