<?php
namespace App\Http\Controllers;

use App\Models\UserSalary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserSalaryController extends Controller
{
    /**
     * Add First Salary / Joining Salary
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'        => 'required|exists:users,id',
            'salary'         => 'required|numeric|min:0',
            'effective_from' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // Check if current salary already exists
        $existing = UserSalary::where('user_id', $request->user_id)
            ->whereNull('effective_to')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Current salary already exists. Use update API.',
            ], 400);
        }

        $salary = UserSalary::create([
            'user_id'        => $request->user_id,
            'salary'         => $request->salary,
            'effective_from' => $request->effective_from,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salary added successfully',
            'data'    => $salary,
        ]);
    }

    /**
     * Update Salary (Maintain History)
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
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // Close current salary
        $currentSalary = UserSalary::where('user_id', $request->user_id)
            ->whereNull('effective_to')
            ->first();

        if ($currentSalary) {
            $currentSalary->update([
                'effective_to' => now()->toDateString(),
            ]);
        }

        // Insert new salary
        $newSalary = UserSalary::create([
            'user_id'        => $request->user_id,
            'salary'         => $request->salary,
            'effective_from' => $request->effective_from,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salary updated successfully',
            'data'    => $newSalary,
        ]);
    }

    /**
     * Get Salary History
     */
    public function history($user_id)
    {
        $salaries = UserSalary::where('user_id', $user_id)
            ->orderBy('effective_from', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $salaries,
        ]);
    }

    /**
     * Get Current Salary
     */
    public function current($user_id)
    {
        $salary = UserSalary::where('user_id', $user_id)
            ->whereNull('effective_to')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $salary,
        ]);
    }
}
