<?php

namespace App\Http\Middleware;

use App\Services\OwnerSales\OwnerSaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGymSubscriptionIsActive
{
    public function __construct(private readonly OwnerSaleService $ownerSales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->hasRole('owner') || ! $user->gym_id) {
            return $next($request);
        }

        if ($request->routeIs('owner-sales.access-expired', 'logout')) {
            return $next($request);
        }

        if ($this->ownerSales->gymHasAccess((int) $user->gym_id)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('owner_sales.access_expired')], 402);
        }

        return redirect()->route('owner-sales.access-expired', [
            'locale' => $request->route('locale') ?? app()->getLocale(),
        ]);
    }
}
