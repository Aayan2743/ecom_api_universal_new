<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method'     => 'required|string',
            'paid_amount'        => 'required|numeric|min:0',

            'customer_name'      => 'nullable|string|max:255',
            'customer_phone'     => 'nullable|string|max:20',

            'items'              => 'required|array|min:1',

            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'required|integer|exists:variant_combinations,id',
            'items.*.qty'        => 'required|integer|min:1',
        ];
    }
}
