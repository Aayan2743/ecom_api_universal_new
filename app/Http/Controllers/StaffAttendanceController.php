<?php
namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffAttendanceController extends Controller
{
    public function getAttendance(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'month'   => 'required', // YYYY-MM
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $records = Attendance::where('user_id', $request->user_id)
            ->whereMonth('date', substr($request->month, 5, 2))
            ->whereYear('date', substr($request->month, 0, 4))
            ->get()
            ->keyBy('date');

        return response()->json([
            'data' => $records,
        ]);
    }

    // SAVE / UPDATE ATTENDANCE
    public function saveAttendance(Request $request)
    {
        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'date'      => 'required|date',
            'status'    => 'required|in:present,absent,leave,ot,c_off',
            'in_time'   => 'nullable',
            'out_time'  => 'nullable',
            'ot_amount' => 'nullable|numeric|min:0',
        ]);

        Attendance::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'date'    => $request->date,
            ],
            [
                'status'    => $request->status,
                'in_time'   => $request->in_time,
                'out_time'  => $request->out_time,
                'ot_amount' => $request->status === 'ot'
                    ? $request->ot_amount
                    : null,
            ]
        );

        return response()->json(['success' => true]);
    }
}
