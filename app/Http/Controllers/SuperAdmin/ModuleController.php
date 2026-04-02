<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;



use App\Models\Module;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;


class ModuleController extends Controller
{
     public function index()
    {
        $modules = Module::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    // ✅ CREATE MODULE
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|unique:modules,name',
            'label' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $module = Module::create([
            'name'  => strtolower($request->name),
            'label' => $request->label,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data'    => $module
        ]);
    }

    // ✅ UPDATE MODULE
    public function update(Request $request, $id)
    {
        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'  => 'required|unique:modules,name,' . $id,
            'label' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $module->update([
            'name'  => strtolower($request->name),
            'label' => $request->label,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
        ]);
    }

    // ✅ DELETE MODULE
    public function destroy($id)
    {
        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        // 🔥 delete related permissions also
        Permission::where('name', 'like', $module->name . '.%')->delete();

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }

    // ✅ TOGGLE MODULE
    public function toggle($id)
    {
        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        $module->is_active = !$module->is_active;
        $module->save();

        return response()->json([
            'success' => true,
            'message' => 'Module status updated',
            'status'  => $module->is_active
        ]);
    }

    // =============================
    // 🔥 ADD PERMISSION TO MODULE
    // =============================
    public function addPermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|exists:modules,name',
            'action' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $permissionName = $request->module . '.' . $request->action;

        $permission = Permission::firstOrCreate([
            'name' => $permissionName
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission added',
            'data' => $permission
        ]);
    }

    // =============================
    // 🔥 GET MODULE + PERMISSIONS
    // =============================
    public function modulesWithPermissions()
    {
        $modules = Module::all();
        $permissions = Permission::all();

        $data = [];

        foreach ($modules as $mod) {
            $data[$mod->name] = [
                'label' => $mod->label,
                'is_active' => $mod->is_active,
                'permissions' => []
            ];

            foreach ($permissions as $perm) {
                if (str_starts_with($perm->name, $mod->name . '.')) {
                    $data[$mod->name]['permissions'][] = $perm;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
