<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    /**
     * Get all permissions assigned to a role
     */
    public function getRolePermissions(Role $role)
    {
        $permissions = $role->permissions()->get();
        return successResponse($permissions, 'Role permissions retrieved successfully');
    }

    /**
     * Get all roles that have a specific permission
     */
    public function getPermissionRoles(Permission $permission)
    {
        $roles = $permission->roles()->get();
        return successResponse($roles, 'Permission roles retrieved successfully');
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissionsToRole(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => ['required', Rule::exists('permissions', 'id')],
        ]);

        try {
            DB::transaction(function () use ($request, $role) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                $role->syncPermissions($permissions);
            });

            return successResponse(null, 'Permissions assigned to role successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove a permission from a role
     */
    public function removePermissionFromRole(Role $role, Permission $permission)
    {
        try {
            $role->revokePermissionTo($permission);
            return successResponse(null, 'Permission removed from role successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Check if a role has a specific permission
     */
    public function checkRoleHasPermission(Request $request, Role $role)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $hasPermission = $role->hasPermissionTo($request->permission);
        return successResponse(['has_permission' => $hasPermission], 'Role permission check completed');
    }
}
