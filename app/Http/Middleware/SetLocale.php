<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'prs', 'ps'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = Session::get('locale', config('app.locale', 'en'));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);
        Carbon::setLocale($locale === 'prs' ? 'fa' : $locale);

        return $next($request);
    }
}
