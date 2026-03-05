<?php
namespace App\Http\Controllers;

use App\Models\CustomerCare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerCareController extends Controller
{
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'nullable|string|max:255',
            'time'            => 'required|string|max:100',
            'working_days'    => 'required|string|max:100',
            'whatsapp_number' => 'required|digits_between:8,15',
            'email'           => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->first(),
            ], 422);
        }

        // Check existing record
        $customerCare = CustomerCare::first();

        if ($customerCare) {
            // Update existing
            $customerCare->update([
                'title'           => $request->title ?? 'CUSTOMER CARE',
                'time'            => $request->time,
                'working_days'    => $request->working_days,
                'whatsapp_number' => $request->whatsapp_number,
                'email'           => $request->email,
            ]);
        } else {
            // Create new (only once)
            $customerCare = CustomerCare::create([
                'title'           => $request->title ?? 'CUSTOMER CARE',
                'time'            => $request->time,
                'working_days'    => $request->working_days,
                'whatsapp_number' => $request->whatsapp_number,
                'email'           => $request->email,
            ]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Customer Care details saved successfully',
            'data'    => $customerCare,
        ]);
    }

    public function show()
    {
        $customerCare = CustomerCare::first();

        return response()->json([
            'status' => true,
            'data'   => $customerCare,
        ]);
    }
}
