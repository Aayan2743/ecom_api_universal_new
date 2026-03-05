<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Validator;

class StaffUserController extends Controller
{
    /**
     * List Staff (pagination + search)
     */

    public function index(Request $request)
    {
        $search  = $request->get('search');
        $perPage = $request->get('per_page', 10);

        $query = User::with('currentSalary')
            ->where('role', 'employee');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $users->items(),
            'meta'    => [
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
            ],
        ]);
    }

    /**
     * Create Staff
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'phone'        => 'required|string|max:15|unique:users,phone',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:6',
            'role'         => 'required|string',
            'salary'       => 'required|numeric|min:0',
            'joining_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            // 1️⃣ Create Staff User
            $user = User::create([
                'name'     => $request->name,
                'phone'    => $request->phone,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => $request->role,
            ]);

            // 2️⃣ Insert Joining Salary
            UserSalary::create([
                'user_id'        => $user->id,
                'salary'         => $request->salary,
                'effective_from' => $request->joining_date,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Staff added successfully',
                'data'    => $user,
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * UPDATE SALARY
     */

    public function updateSalary(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id'        => 'required|exists:users,id',
            'salary'         => 'required|numeric|min:0',
            'effective_from' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            // Close current salary
            UserSalary::where('user_id', $request->user_id)
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => now()->toDateString(),
                ]);

            // Insert new salary
            UserSalary::create([
                'user_id'        => $request->user_id,
                'salary'         => $request->salary,
                'effective_from' => $request->effective_from,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salary updated successfully',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

/**
 * UPDATE STAFF
 */
    public function updateStaff(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255',
            'phone'  => 'required|digits:10|unique:users,phone,' . $id,
            'email'  => 'required|email|unique:users,email,' . $id,
            'role'   => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::findOrFail($id);

        $user->update([
            'name'   => $request->name,
            'phone'  => $request->phone,
            'email'  => $request->email,
            'role'   => $request->role,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff updated successfully',
        ]);
    }

    /**
     * Show Staff
     */
    public function show($id)
    {
        $user = User::where('role', 'employee')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    /**
     * Update Staff
     */
    public function update(Request $request, $id)
    {
        $user = User::where('role', 'employee')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'required',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'role'  => 'required|in:employee,employeer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
            ], 422);
        }

        $user->update($request->only('name', 'email', 'phone', 'role'));

        return response()->json([
            'success' => true,
            'message' => 'Staff updated successfully',
            'data'    => $user,
        ]);
    }

    /**
     * Soft Delete Staff
     */
    public function destroy($id)
    {
        $user = User::where('role', 'employee')->findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff deleted successfully',
        ]);
    }
}
