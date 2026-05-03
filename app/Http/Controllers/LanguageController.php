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

        return back()->withCookie($cookie);
    }
}
