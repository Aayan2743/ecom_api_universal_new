<?php
namespace App\Http\Controllers;

use App\Models\ProductVariation;
use App\Models\ProductVariationValue;
use Illuminate\Http\Request;
use Validator;

class ProductVariationValueController extends Controller
{
    public function indexddd()
    {
        return ProductVariationValue::with('variation')->get();
    }

    public function index()
    {
        $variations = ProductVariation::with('values')->get();

        $data = $variations->map(function ($variation) {
            return [
                'id'     => $variation->id,
                'name'   => $variation->name,
                'type'   => $variation->type,
                'values' => $variation->values->map(function ($val) {
                    return [
                        'id'         => $val->id,
                        'value'      => $val->value,
                        'color_code' => $val->color_code,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function store(Request $request, $variationId)
    {
        $validator = Validator::make($request->all(), [
            'value'      => 'required|string|max:255',
            'color_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $value = ProductVariationValue::create([
            'variation_id' => $variationId,
            'value'        => $request->value,
            'color_code'   => $request->color_code,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $value,
        ]);
    }

    public function update(Request $request, $id)
    {
        $value = ProductVariationValue::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'value'      => 'required|string|max:255',
            'color_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $value->update([
            'value'      => $request->value,
            'color_code' => $request->color_code,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $value,
        ]);
    }

    public function destroy($id)
    {
        ProductVariationValue::where('id', $id)->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
