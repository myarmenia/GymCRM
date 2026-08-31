<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\StableUuid;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ownerEmail = (string) env('OWNER_EMAIL');

        $user = User::updateOrCreate(['email' => $ownerEmail], [
            'name' => 'Owner',
            'surname' => 'OwnerSurname',
            'email' => $ownerEmail,
            'password' => bcrypt(env('OWNER_PASS')),
            ...StableUuid::seedIdentity('users', mb_strtolower($ownerEmail)),
        ]);

        $role = Role::updateOrCreate(['name' => 'owner']);

        $permissions = Permission::pluck('id', 'id')->all();

        $role->syncPermissions($permissions);

        $user->assignRole([$role->id]);
    }
}
