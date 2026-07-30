<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            /* Permission Management */
            ['name' => 'Permission Index', 'group_name' => 'Permission Management Permissions'],
            ['name' => 'Permission List', 'group_name' => 'Permission Management Permissions'],
            ['name' => 'Permission Create', 'group_name' => 'Permission Management Permissions'],
            ['name' => 'Permission Update', 'group_name' => 'Permission Management Permissions'],
            ['name' => 'Permission Delete', 'group_name' => 'Permission Management Permissions'],

            /* Role Management */
            ['name' => 'Role Index', 'group_name' => 'Role Management Permissions'],
            ['name' => 'Role List', 'group_name' => 'Role Management Permissions'],
            ['name' => 'Role Create', 'group_name' => 'Role Management Permissions'],
            ['name' => 'Role Update', 'group_name' => 'Role Management Permissions'],
            ['name' => 'Role Delete', 'group_name' => 'Role Management Permissions'],

            /* User Management */
            ['name' => 'User Index', 'group_name' => 'User Management Permissions'],
            ['name' => 'User List', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Create', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Update', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Delete', 'group_name' => 'User Management Permissions'],
            ['name' => 'User Toggle Status', 'group_name' => 'User Management Permissions'],

            /* Bank Management */
            ['name' => 'Bank Index', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank List', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Create', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Update', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Delete', 'group_name' => 'Bank Management Permissions'],
            ['name' => 'Bank Toggle Status', 'group_name' => 'Bank Management Permissions'],

            /* Customer Management */
            ['name' => 'Customer Index', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer List', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Create', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Update', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Delete', 'group_name' => 'Customer Management Permissions'],
            ['name' => 'Customer Toggle Status', 'group_name' => 'Customer Management Permissions'],

            /* Sale Management */
            ['name' => 'Sale Index', 'group_name' => 'Sale Management Permissions'],
            ['name' => 'Sale List', 'group_name' => 'Sale Management Permissions'],
            ['name' => 'Sale Create', 'group_name' => 'Sale Management Permissions'],
            ['name' => 'Sale Update', 'group_name' => 'Sale Management Permissions'],
            ['name' => 'Sale Delete', 'group_name' => 'Sale Management Permissions'],

            /* Cheque Management */
            ['name' => 'Cheque Index', 'group_name' => 'Cheque Management Permissions'],
            ['name' => 'Cheque List', 'group_name' => 'Cheque Management Permissions'],
            ['name' => 'Cheque Create', 'group_name' => 'Cheque Management Permissions'],
            ['name' => 'Cheque Update Status', 'group_name' => 'Cheque Management Permissions'],
            ['name' => 'Cheque Delete', 'group_name' => 'Cheque Management Permissions'],

            /* Payment Management */
            ['name' => 'Payment Index', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Create', 'group_name' => 'Payment Management Permissions'],
            ['name' => 'Payment Delete', 'group_name' => 'Payment Management Permissions'],

            /* Expense Management */
            ['name' => 'Expense Index', 'group_name' => 'Expense Management Permissions'],
            ['name' => 'Expense Create', 'group_name' => 'Expense Management Permissions'],
            ['name' => 'Expense Update', 'group_name' => 'Expense Management Permissions'],
            ['name' => 'Expense Delete', 'group_name' => 'Expense Management Permissions'],

            /* Dashboard */
            ['name' => 'Dashboard Index', 'group_name' => 'Dashboard Permissions'],

            /* Reports */
            ['name' => 'Report Sales', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Customer Statement', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Cheques', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Payments', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Expense Summary', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Monthly Summary', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report Dues Aging', 'group_name' => 'Reports Permissions'],
            ['name' => 'Report PnL', 'group_name' => 'Reports Permissions'],

            /* Activity Log Management */
            ['name' => 'ActivityLog Index', 'group_name' => 'Activity Log Management Permissions'],
            ['name' => 'ActivityLog Show', 'group_name' => 'Activity Log Management Permissions'],

            /* Database Backup */
            ['name' => 'Database Export', 'group_name' => 'Database Management Permissions'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
                'guard_name' => 'api',
            ]);
        }

        Role::firstOrCreate(['guard_name' => 'api', 'name' => 'Super Admin', 'is_protected' => true]);
        
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        if ($superAdminRole) {
            $superAdminRole->update(['is_protected' => true]);
            $allPermissions = Permission::all();
            $superAdminRole->syncPermissions($allPermissions);
        }
    }
}
