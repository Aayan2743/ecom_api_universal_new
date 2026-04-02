<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;



use Spatie\Permission\Models\Role;


use Illuminate\Support\Facades\Validator;

use Spatie\Permission\Models\Permission;

class SuperAdminRoleController extends Controller
{
     public function index()
    {
        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    // =============================
    // ✅ CREATE ROLE
    // =============================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $role = Role::create([
            'name' => strtolower($request->name)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ]);
    }

    // =============================
    // ✅ GET ROLE PERMISSIONS
    // =============================
    public function getRolePermissions($roleName)
    {
        try {
            $role = Role::findByName($roleName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $permissions = Permission::all()->map(function ($perm) use ($role) {
            return [
                'id'      => $perm->id,
                'name'    => $perm->name,
                'checked' => $role->hasPermissionTo($perm->name),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    // =============================
    // ✅ ASSIGN PERMISSIONS TO ROLE
    // =============================
    public function assignPermissions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $role = Role::findByName($request->role);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        // 🔥 MAIN LINE YOU ASKED
        $role->syncPermissions($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully'
        ]);
    }

    // =============================
    // ✅ DELETE ROLE
    // =============================
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
