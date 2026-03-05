<?php
namespace App\Http\Controllers;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Validator;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => Coupon::orderBy('id', 'desc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'         => 'required|string|max:50|unique:coupons,code',
            'type'         => 'required|in:percent,flat',
            'value'        => 'required|numeric|min:0',
            'min_order'    => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'expiry_date'  => 'nullable|date',
            'is_active'    => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => Coupon::create($validator->validated()),
        ]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code'         => 'required|string|max:50|unique:coupons,code,' . $id,
            'type'         => 'required|in:percent,flat',
            'value'        => 'required|numeric|min:0',
            'min_order'    => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'expiry_date'  => 'nullable|date',
            'is_active'    => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $coupon->update($validator->validated());

        return response()->json([
            'success' => true,
            'data'    => $coupon,
        ]);
    }

    public function destroy($id)
    {
        Coupon::where('id', $id)->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function apply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'   => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request',
            ], 422);
        }

        $coupon = Coupon::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive coupon',
            ], 400);
        }

        // ✅ Expiry check
        if ($coupon->expiry_date && Carbon::parse($coupon->expiry_date)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon has expired',
            ], 400);
        }

        // ✅ Minimum order check
        if ($request->amount < $coupon->min_order) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount ₹' . $coupon->min_order . ' required',
            ], 400);
        }

        /* ================= CALCULATE DISCOUNT ================= */

        $discount = 0;

        if ($coupon->type === 'percent') {
            $discount = ($request->amount * $coupon->value) / 100;

            if ($coupon->max_discount) {
                $discount = min($discount, $coupon->max_discount);
            }
        }

        if ($coupon->type === 'flat') {
            $discount = $coupon->value;
        }

        $discount = min($discount, $request->amount);

        $finalAmount = $request->amount - $discount;

        return response()->json([
            'success'     => true,
            'coupon_code' => $coupon->code,
            'discount'    => round($discount, 2),
            'finalAmount' => round($finalAmount, 2),
            'coupon_type' => $coupon->type,
        ]);
    }
}