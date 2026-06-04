<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'g_name' => 'owner',
            ],
            [
                'name' => 'admin',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'manager',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'staff',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'accountant',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'cleaner',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'trainer',
                'g_name' => 'super_admin',
            ],
            [
                'name' => 'owner',
                'g_name' => 'owner',
            ],
            [
                'name'=> 'sales_manager',
                'g_name' => 'super_admin',
            ]
        ];


        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
