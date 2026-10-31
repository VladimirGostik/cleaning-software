<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Push\PushMessage;

interface SendsPush
{
    /**
     * @param  list<string>  $tokens
     * @return list<string> tokens the provider reported as unusable (unregistered / invalid)
     */
    public function send(PushMessage $message, array $tokens): array;
}
