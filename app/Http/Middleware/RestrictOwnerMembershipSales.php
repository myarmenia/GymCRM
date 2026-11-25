<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictOwnerMembershipSales
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->hasRole('owner') && ! $request->routeIs('membership_sale.list', 'sale_contract.show', 'sale_contract.download', 'sale_contract.download_white', 'sale_contract.settings', 'sale_contract.settings.update'), 403);

        return $next($request);
    }
}
