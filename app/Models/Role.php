<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'g_name',
    ];

    public function scopeAvailableFor($query, $user)
    {
        $roles = $user->getRoleNames();

        if ($roles->contains('admin')) {
            $roles->push('super_admin');
        }

        return $query->when($roles, function ($q) use ($roles) {
            $q->whereIn('g_name', $roles);
        });

    }
}
