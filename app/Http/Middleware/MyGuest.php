<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyGuest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $locale = $request->route('locale') ?? 'en'; // Получаем локаль из маршрута
            $user = Auth::user(); // Получаем текущего пользователя

            return $user->hasVerifiedEmail()
                ? redirect()->route('welcome', ['locale' => $locale])// Если email верифицирован, отправляем в dashboard
                : redirect()->route('dashboard', ['locale' => $locale]); // Если нет — на welcome
        }

        return $next($request);
    }
}
