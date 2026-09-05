<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLanguage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Authenticated user locale
        if ($request->user() && SupportedLanguage::isSupported($request->user()->locale)) {
            return $request->user()->locale;
        }

        // 2. Session
        $sessionLocale = $request->session()->get('locale');
        if (is_string($sessionLocale) && SupportedLanguage::isSupported($sessionLocale)) {
            return $sessionLocale;
        }

        // 3. Cookie
        $cookieLocale = $request->cookie('locale');
        if (is_string($cookieLocale) && SupportedLanguage::isSupported($cookieLocale)) {
            return $cookieLocale;
        }

        // 4. Preferred language from Accept-Language header
        $preferred = $request->getPreferredLanguage(SupportedLanguage::getCodes());
        if ($preferred !== null && SupportedLanguage::isSupported($preferred)) {
            return $preferred;
        }

        // 5. Default
        return SupportedLanguage::getDefault()->value;
    }
}
