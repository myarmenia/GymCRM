<?php

namespace App\Http\Middleware;

use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobilePerson
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Person, 403, 'Mobile access is restricted to persons.');

        return $next($request);
    }
}
