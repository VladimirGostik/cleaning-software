<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SupportedLanguage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class LanguageController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! SupportedLanguage::isSupported($locale)) {
            abort(404);
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);

        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        Cookie::queue('locale', $locale, 60 * 24 * 30);

        return redirect()->back()->with('info', __('app.language_changed'));
    }
}
