<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserRolePermissionController extends Controller
{
    /**
     * Get all roles assigned to an user
     */
    public function getUserRoles(User $user)
    {
        $roles = $user->roles()->get();
        return successResponse($roles, 'User roles retrieved successfully');
    }

    /**
     * Get all direct permissions assigned to an user
     */
    public function getUserPermissions(User $user)
    {
        $permissions = $user->permissions()->get();
        return successResponse($permissions, 'User permissions retrieved successfully');
    }

    /**
     * Get all permissions an user has (including those from roles)
     */
    public function getUserAllPermissions(User $user)
    {
        $permissions = $user->getAllPermissions();
        return successResponse($permissions, 'All user permissions retrieved successfully');
    }

    /**
     * Assign roles to an user
     */
    public function assignRolesToUser(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => ['required', Rule::exists('roles', 'id')],
        ]);

        try {
            DB::transaction(function () use ($request, $user) {
                $roles = Role::whereIn('id', $request->roles)->get();
                $user->syncRoles($roles);
            });

            return successResponse(null, 'Roles assigned to admin successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Assign direct permissions to an user
     */
    public function assignPermissionsToUser(Request $request, User $user)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => ['required', Rule::exists('permissions', 'id')],
        ]);

        try {
            DB::transaction(function () use ($request, $user) {
                $permissions = Permission::whereIn('id', $request->permissions)->get();
                $user->syncPermissions($permissions);
            });

            return successResponse(null, 'Permissions assigned to admin successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove a role from an user
     */
    public function removeRoleFromUser(User $user, Role $role)
    {
        try {
            $user->removeRole($role);
            return successResponse(null, 'Role removed from user successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove a direct permission from an user
     */
    public function removePermissionFromUser(User $user, Permission $permission)
    {
        try {
            $user->revokePermissionTo($permission);
            return successResponse(null, 'Permission removed from user successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Check if an user has a specific role
     */
    public function checkUserHasRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $hasRole = $user->hasRole($request->role);
        return successResponse(['has_role' => $hasRole], 'User role check completed');
    }

    /**
     * Check if an user has a specific permission
     */
    public function checkUserHasPermission(Request $request, User $user)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $hasPermission = $user->hasPermissionTo($request->permission);
        return successResponse(['has_permission' => $hasPermission], 'User permission check completed');
    }
}
