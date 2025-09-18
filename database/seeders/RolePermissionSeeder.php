<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Start a transaction to ensure all operations succeed or fail together
        DB::transaction(function () {
            // Create Admin role if it doesn't exist
            $userRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'user']);

            // Define permissions by module
            $userPermissions = $this->getPermissions();


            foreach ($userPermissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'user'
                ]);
            }

            // Assign all permissions to admin role
            $userRole->syncPermissions(Permission::where('guard_name', 'user')->get());

            // Assign admin role to all existing admins

            $user = User::find(1);
            $user->assignRole($userRole);

            $this->command->info('Admin role created and permissions assigned successfully!');
        });
    }

    /**
     * Get all permissions for the system
     *
     * @return array
     */
    private function getPermissions(): array
    {
    
        $generalPermissions = [
             // Admin management
            'admin-view',
            'admin-create',
            'admin-edit',
            'admin-list',
            'admin-delete',
            'admin-bulk-delete',
            'admin-permanent-delete',
            'admin-restore',
            'admin-export',

            // Role management
            'role-view',
            'role-create',
            'role-edit',
            'role-list',
            'role-delete',
            'role-bulk-delete',
            'role-permanent-delete',
            'role-restore',
            'role-export',
            // User management
            'user-view',
            'user-create',
            'user-edit',
            'user-list',
            'user-delete',
            'user-bulk-delete',
            'brand-permanent-delete',
            'user-restore',
            'user-export',

            // Permission management
            'permission-view',
            'permission-list',

            // Status management
            'status-view',
            'status-create',
            'status-edit',
            'status-list',
            'status-delete',
            'status-bulk-delete',
            'status-permanent-delete',
            'status-restore',
            'status-export',

            // Country management
            'country-view',
            'country-create',
            'country-edit',
            'country-list',
            'country-delete',
            'country-restore',
            'country-export',

            // City management
            'state-view',
            'state-create',
            'state-edit',
            'state-list',
            'state-delete',
            'state-restore',
            'state-export',

            // State management
            'state-view',
            'state-create',
            'state-edit',
            'state-list',
            'state-delete',
            'state-restore',
            'state-export',

            // Business management
            'business-view',
            'business-create',
            'business-edit',
            'business-list',
            'business-delete',
            'business-restore',
            'business-export',

            // Business management
            'business-view',
            'business-create',
            'business-edit',
            'business-list',
            'business-delete',
            'business-restore',
            'business-export',

            // Service management
            'service-view',
            'service-create',
            'service-edit',
            'service-list',
            'service-delete',
            'service-restore',
            'service-export',

            // Address management
            'address-view',
            'address-create',
            'address-edit',
            'address-list',
            'address-delete',
            'address-restore',
            'address-export',

            // Card management
            'card-view',
            'card-create',
            'card-edit',
            'card-list',
            'card-delete',
            'card-restore',
            'card-export',

            // Business management
            'business-view',
            'business-create',
            'business-edit',
            'business-list',
            'business-delete',
            'business-restore',
            'business-export',

            // Package management
            'package-view',
            'package-create',
            'package-edit',
            'package-list',
            'package-delete',
            'package-restore',
            'package-export',

            // Lead management
            'lead-view',
            'lead-create',
            'lead-edit',
            'lead-list',
            'lead-delete',
            'lead-restore',
            'lead-export',
        ];
        return $generalPermissions;
    }
}
