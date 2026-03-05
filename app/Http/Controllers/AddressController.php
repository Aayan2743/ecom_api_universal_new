<?php
namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $addresses,
        ]);
    }

    /* ================= CREATE ADDRESS ================= */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'phone'   => 'required|digits:10',
            'address' => 'required|string',
            'city'    => 'required|string|max:100',
            'state'   => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'pincode' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $address = Address::create([
            'user_id' => $request->user()->id,
            'name'    => $request->name,
            'phone'   => $request->phone,
            'address' => $request->address,
            'city'    => $request->city,
            'state'   => $request->state,
            'country' => $request->country ?? 'India',
            'pincode' => $request->pincode,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $address,
            'message' => 'Address added successfully',
        ]);
    }

    public function posstore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'name'    => 'required|string|max:255',
            'phone'   => 'required|digits:10',
            'address' => 'required|string',
            'city'    => 'required|string|max:100',
            'state'   => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'pincode' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // ✅ If first address → make default
        $isFirst = Address::where('user_id', $request->user_id)->count() == 0;

        $address = Address::create([
            'user_id'    => $request->user_id,
            'name'       => $request->name,
            'phone'      => $request->phone,
            'address'    => $request->address,
            'city'       => $request->city,
            'state'      => $request->state,
            'country'    => $request->country ?? 'India',
            'pincode'    => $request->pincode,
            'is_default' => $isFirst ? 1 : 0,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $address,
            'message' => 'Address added successfully',
        ]);
    }

    /* ================= UPDATE ADDRESS ================= */
    public function update(Request $request, $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'phone'   => 'required|digits:10',
            'address' => 'required|string',
            'city'    => 'required|string|max:100',
            'state'   => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'pincode' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $address->update($request->only([
            'name',
            'phone',
            'address',
            'city',
            'state',
            'country',
            'pincode',
        ]));

        return response()->json([
            'success' => true,
            'data'    => $address,
            'message' => 'Address updated successfully',
        ]);
    }

    /* ================= DELETE ADDRESS ================= */
    public function destroy(Request $request, $id)
    {
        $address = Address::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully',
        ]);
    }

    /* ================= SET DEFAULT ADDRESS ================= */
    public function setDefault(Request $request, $id)
    {
        Address::where('user_id', $request->user()->id)
            ->update(['is_default' => false]);

        Address::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default address updated',
        ]);
    }
}