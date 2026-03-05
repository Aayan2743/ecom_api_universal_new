<?php
namespace App\Http\Controllers;

use App\Models\ContactSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactSettingController extends Controller
{
    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'          => 'nullable|string|max:255',
            'description'    => 'nullable|string',
            'address'        => 'required|string|max:255',
            'phone_1'        => 'required|digits_between:8,15',
            'phone_2'        => 'nullable|digits_between:8,15',
            'working_days'   => 'required|string|max:100',
            'working_hours'  => 'required|string|max:100',
            'weekend_note'   => 'nullable|string|max:100',
            'google_map_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->first(),
            ], 422);
        }

        // Only one record allowed (ID = 1)
        $contact = ContactSetting::updateOrCreate(
            ['id' => 1],
            [
                'title'          => $request->title ?? 'Contact Hamsini Silks',
                'description'    => $request->description,
                'address'        => $request->address,
                'phone_1'        => $request->phone_1,
                'phone_2'        => $request->phone_2,
                'working_days'   => $request->working_days,
                'working_hours'  => $request->working_hours,
                'weekend_note'   => $request->weekend_note,
                'google_map_url' => $request->google_map_url,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Contact settings saved successfully',
            'data'    => $contact,
        ]);
    }

    public function show()
    {
        $contact = ContactSetting::first();

        return response()->json([
            'status' => true,
            'data'   => $contact,
        ]);
    }
}
