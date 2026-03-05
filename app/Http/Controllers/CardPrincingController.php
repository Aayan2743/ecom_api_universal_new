<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\CardPricing;


class CardPrincingController extends Controller
{
     public function show()
    {
        $pricing = CardPricing::first();

        return response()->json([
            'message' => 'Card pricing fetched successfully',
            'data' => $pricing
        ]);
    }

    /* ======================
       CREATE / UPDATE (UPSERT)
    ====================== */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_amount' => 'required|numeric|min:0',
            'min_card'    => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Single row pattern
        $pricing = CardPricing::first() ?? new CardPricing();

        $pricing->card_amount = $request->card_amount;
        $pricing->min_card = $request->min_card;
        $pricing->save();

        return response()->json([
            'message' => 'Card pricing saved successfully',
            'data' => $pricing
        ]);
    }
}