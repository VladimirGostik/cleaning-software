<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SupportedLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! SupportedLanguage::isSupported($locale)) {
            $locale = SupportedLanguage::default()->value;
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        if ($user = $request->user()) {
            $user->locale = $locale;
            $user->save();
        }

        $cookie = Cookie::create('locale', $locale, now()->addDays(30)->getTimestamp());

        $previous = url()->previous();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($appHost === null || empty($previous) || str_contains($previous, '/language/')) {
            $previous = '/dashboard';
        } else {
            $prevHost = parse_url($previous, PHP_URL_HOST);
            if ($prevHost !== $appHost) {
                $previous = '/dashboard';
            }
        }

        return redirect()->to($previous)->withCookie($cookie);
    }
}
