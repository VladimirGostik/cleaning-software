<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLanguage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiLocaleMiddleware
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $userLocale = $request->user()?->locale;

        $locale = is_string($userLocale) && SupportedLanguage::isSupported($userLocale)
            ? $userLocale
            : $request->getPreferredLanguage(SupportedLanguage::getCodes()) ?? SupportedLanguage::getDefault()->value;

        app()->setLocale($locale);

        return $next($request);
    }
}
