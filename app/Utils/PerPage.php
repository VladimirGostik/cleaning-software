<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Http\Request;

final class PerPage
{
    public const int DEFAULT = 25;

    public const int MAX = 100;

    public static function clamp(Request $request): Request
    {
        $request->merge(['per_page' => max(1, min(self::MAX, $request->integer('per_page', self::DEFAULT)))]);

        return $request;
    }
}
