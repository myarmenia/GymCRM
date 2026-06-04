<?php

namespace App\Http\Middleware;

use Closure;

class CheckGymAccess
{
    public function handle($request, Closure $next, $modelClass, $paramName = 'id')
    {

    //dd($modelClass, $paramName);
        $user = auth()->user();
        // Admin mozhet vse
        //dd($user);
        if ($user->hasRole('owner')) {
            return $next($request);
        }

        if (!$user->gym_id) {
           abort(403, 'You are not assigned to any gym.');
        }
        $model = app("App\\Models\\$modelClass")->findOrFail($request->route($paramName));


        if ($model->gym_id !== $user->gym_id) {
            abort(403, 'This resource does not belong to your gym.');
        }

        return $next($request);
    }
}
