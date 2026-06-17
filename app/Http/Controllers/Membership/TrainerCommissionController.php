<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\TrainerCommission;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TrainerCommissionController extends Controller
{
    public function list()
    {
        $user = Auth::user();

        $commissions = TrainerCommission::query()
            ->with([
                'trainer',
                'personMembership.person',
                'membershipSale.membershipPlan.translations',
            ])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipSale', function ($q) use ($user) {
                    $q->where('gym_id', $user->gym_id);
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('TrainerCommissions/List', [
            'commissions' => $commissions,
        ]);
    }
}
