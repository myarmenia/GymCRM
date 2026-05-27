<?php

namespace Database\Seeders;

use App\Models\User;
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
        $user = User::updateOrCreate(['email' => env('OWNER_EMAIL')], [
            'name' => 'Owner',
            'surname' => 'OwnerSurname',
            'email' => env('OWNER_EMAIL'),
            'password' => bcrypt(env('OWNER_PASS'))
        ]);

        $role = Role::updateOrCreate(['name' => 'owner']);

        $permissions = Permission::pluck('id', 'id')->all();

        $role->syncPermissions($permissions);

        $user->assignRole([$role->id]);
    }
}
