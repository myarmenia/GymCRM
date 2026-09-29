<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * End authenticated sessions as soon as an employee is deactivated.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->active === false) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $locale = $request->route('locale') ?? app()->getLocale();

            return redirect("/{$locale}/login")
                ->withErrors(['email' => trans('auth.failed')]);
        }

        return $next($request);
    }
}
