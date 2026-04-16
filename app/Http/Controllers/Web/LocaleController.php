<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $locale = $request->input('locale', 'en');

        if (! in_array($locale, ['en', 'ar'], true)) {
            $locale = 'en';
        }

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
