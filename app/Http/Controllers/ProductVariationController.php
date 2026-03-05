<?php
namespace App\Http\Controllers;

use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Validator;

class ProductVariationController extends Controller
{
    public function index()
    {
        $variations = ProductVariation::with('values')->get();

        $data = $variations->map(function ($v) {
            return [
                'id'               => $v->id,
                'name'             => $v->name,
                'type'             => $v->type,
                'variation_values' => $v->values, // 👈 IMPORTANT
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,color',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $variation = ProductVariation::create($validator->validated());

        return response()->json([
            'success' => true,
            'data'    => $variation,
        ]);
    }

    public function update(Request $request, $id)
    {
        $variation = ProductVariation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:text,color',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $variation->update($validator->validated());

        return response()->json([
            'success' => true,
            'data'    => $variation,
        ]);
    }

    public function destroy($id)
    {
        ProductVariation::where('id', $id)->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
