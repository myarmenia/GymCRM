<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = SupportedLocales::resolve($request);

        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
