<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
      public function createRole(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // create role
        $role = Role::create(['name' => $request->name]);

        // return response
        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role
        ]);
    }

    // ✅ 2. Create Permission
    public function createPermission(Request $request)
    {

          $validator = Validator::make($request->all(), [
           'name' => 'required|unique:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $permission = Permission::create(['name' => $request->name]);

        return response()->json([
            'message' => 'Permission created',
            'data' => $permission
        ]);
    }

    public function getPermissions()
{
    return response()->json(
       Permission::all()
    );
}


    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);
        $res=$role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
            'status' => $res
        ]);
    }


    public function getRolePermissions($role)
{
    $role = Role::findByName($role);

    return response()->json([
        'role' => $role->name,
        'permissions' => $role->permissions // IMPORTANT
    ]);
}


    // ✅ 3. Assign Permission to Role
public function assignPermissionToRole(Request $request)
{
    $validator = Validator::make($request->all(), [
        'role' => 'required',
        'permissions' => 'required|array'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $role = Role::findByName($request->role);

    // 🔥 replace all permissions
    $role->syncPermissions($request->permissions);

    return response()->json([
        'message' => 'Permissions updated successfully'
    ]);
}

    // ✅ 4. Assign Role to User
    public function assignRoleToUser(Request $request)
    {



          $validator = Validator::make($request->all(), [
              'user_id' => 'required',
            'role' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // find user
        $user = User::findOrFail($request->user_id);

        // assign role
        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Role assigned to user'
        ]);
    }

    // ✅ 5. Check Permission (Important)
    public function checkPermission()
    {
        $user = auth()->user();

        if ($user->can('create_product')) {
            return response()->json(['message' => 'Allowed']);
        } else {
            return response()->json(['message' => 'Not Allowed']);
        }
    }

public function getRoles(Request $request)
{
    $search = $request->search;

    $query = Role::query()
        ->where('name', '!=', 'superadmin'); // ✅ exclude superadmin

    if ($search) {
        $query->where('name', 'like', "%{$search}%");
    }

    $roles = $query->latest()->get();

    return response()->json($roles);
}


public function myPermissions()
{
    $user = auth()->user();

     //dd(auth()->user()->getRoleNames());

    return response()->json([
        'permissions' => $user->getAllPermissions()->pluck('name')
    ]);
}




// public function getUsers(Request $request)
// {
//     $users = User::with('roles:id,name') // 🔥 IMPORTANT
//         ->select('id', 'name', 'email')
//         ->get();

//     return response()->json($users);
// }


public function getUsers(Request $request)
{
    $users = User::with('roles:id,name') // optional if still needed
        ->where('role', 'employee') // ✅ filter here
        ->select('id', 'name', 'email', 'role')
        ->get();

    return response()->json($users);
}

public function removeRoleFromUser(Request $request)
{
    $user = User::findOrFail($request->user_id);

    $user->removeRole($request->role);

    return response()->json([
        'message' => 'Role removed successfully'
    ]);
}


public function deletePermission(Request $request,$id)
{


    $permission = Permission::findOrFail($id);

    $permission->delete();

    return response()->json([
        'message' => 'Permission deleted successfully'
    ]);
}



}

