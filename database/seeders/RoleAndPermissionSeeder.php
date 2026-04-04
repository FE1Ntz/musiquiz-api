<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('import artists', 'web');
        Permission::findOrCreate('manage catalog', 'web');

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->givePermissionTo(Permission::all());
    }
}
