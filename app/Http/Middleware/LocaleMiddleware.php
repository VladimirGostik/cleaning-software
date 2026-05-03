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
        $user = $request->user();

        if ($user && SupportedLanguage::isSupported($user->locale)) {
            return $user->locale;
        }

        $session = $request->session()->get('locale');
        if (SupportedLanguage::isSupported($session)) {
            return (string) $session;
        }

        $cookie = $request->cookie('locale');
        if (SupportedLanguage::isSupported($cookie)) {
            return (string) $cookie;
        }

        $browser = $request->getPreferredLanguage(SupportedLanguage::codes());
        if (SupportedLanguage::isSupported($browser)) {
            return (string) $browser;
        }

        return SupportedLanguage::default()->value;
    }
}
