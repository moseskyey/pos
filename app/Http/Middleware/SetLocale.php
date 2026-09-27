<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'sw'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale') ?? setting('locale.language', 'en');
        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'en');

        return $next($request);
    }
}
