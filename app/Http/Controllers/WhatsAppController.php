<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ChatSession;
use App\Services\Messenger360Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class WhatsAppController extends Controller
{

 protected $messenger;

    public function __construct(Messenger360Service $messenger)
    {
        $this->messenger = $messenger;
    }



    public function webhook(Request $request, Messenger360Service $messenger)
{
    Log::info('Webhook Data:', $request->all());



    $phone = $request->input('From');

    if(strlen($phone) == 10){
        $phone = "91".$phone;
    }

    $message = strtolower(trim(
        $request->input('message')
        ?? $request->input('Chat')
        ?? $request->input('text')
    ));

    if(!$phone){
        return response()->json(['status'=>'phone missing']);
    }

    if(!$message){
        return response()->json(['status'=>'message missing']);
    }




    $phone = $request->input('From');
    $message = strtolower(trim($request->input('message') ?? $request->input('Chat')));

    if(!$phone){
        return response()->json(['status'=>'phone missing']);
    }

    $session = ChatSession::firstOrCreate(
        ['phone'=>$phone],
        ['step'=>'start']
    );

    switch($session->step)
    {

        case 'start':
            return $this->sendCategories($phone,$session,$messenger);

        case 'category':

            return $this->selectCategory($phone,$message,$session,$messenger);

        case 'product':
            return $this->selectProduct($phone,$message,$session,$messenger);

        case 'variant':
            return $this->selectVariant($phone,$message,$session,$messenger);

        case 'address':
            return $this->saveAddress($phone,$message,$session,$messenger);

    }

}

private function sendCategories($phone,$session,$messenger)
{

    $categories = \DB::table('categories')
        ->where('is_active',1)
        ->orderBy('sort_order')
        ->get();

    if($categories->isEmpty()){
        $messenger->send($phone,"No categories available.");
        return;
    }

    $text="🛍 *Select Category*\n\n";

    foreach($categories as $key=>$cat){
        $text.=($key+1)." ".$cat->name."\n";
    }

    $messenger->send($phone,$text);

    $session->update([
        'step'=>'category'
    ]);

}

private function selectCategory($phone,$message,$session,$messenger)
{


    // Check if message is number
    if(!is_numeric($message)){
        $messenger->send($phone,"Please reply with the category number.");
        return;
    }

    $categories = \DB::table('categories')
        ->where('is_active',1)
        ->orderBy('sort_order')
        ->get();

    $index = (int)$message - 1;

    if(!isset($categories[$index])){
        $messenger->send($phone,"Invalid option. Please select again.");
        return;
    }

    $category = $categories[$index];

    $products = \DB::table('products')
        ->where('category_id',$category->id)
        ->limit(5)
        ->get();

    $text="📦 *Products*\n\n";

    foreach($products as $key=>$p){
        $text.=($key+1)." ".$p->name."\n";
    }

    $messenger->send($phone,$text);

    $session->update([
        'step'=>'product',
        'data'=>[
            'category_id'=>$category->id
        ]
    ]);

}




private function selectProduct($phone,$message,$session,$messenger)
{

    $data = $session->data;

    $products = \DB::table('products')
        ->where('category_id',$data['category_id'])
        ->limit(5)
        ->get();

    $product = $products[$message-1];

    $variants = \DB::table('product_variant_combinations')
        ->where('product_id',$product->id)
        ->get();

    $text="📦 *".$product->name."*\n\nSelect Variant\n\n";

    foreach($variants as $key=>$v){

        $price = $v->extra_price;

        $text.=($key+1)." ₹".$price."\n";

    }

    $messenger->send($phone,$text);

    $session->update([
        'step'=>'variant',
        'data'=>[
            'product_id'=>$product->id
        ]
    ]);

}

private function selectVariant($phone,$message,$session,$messenger)
{

    $data = $session->data;

    $variants = \DB::table('product_variant_combinations')
        ->where('product_id',$data['product_id'])
        ->get();

    $variant = $variants[$message-1];

    $session->update([
        'step'=>'address',
        'data'=>[
            'variant_id'=>$variant->id
        ]
    ]);

    $messenger->send($phone,"✅ Added to cart\n\nSend delivery address");

}


private function saveAddress($phone,$message,$session,$messenger)
{

    $data = $session->data;

    $variant = \DB::table('product_variant_combinations')
        ->where('id',$data['variant_id'])
        ->first();

    if(!$variant){
        $messenger->send($phone,"Variant not found.");
        return;
    }

    $product = \DB::table('products')
        ->where('id',$variant->product_id)
        ->first();

    $price = $variant->extra_price;

    // Create Order
    $saleId = \DB::table('sales')->insertGetId([

        'invoice_number' => 'WA'.time(),

        'customer_phone' => $phone,

        'shipping_address_snapshot' => json_encode([
            'address'=>$message
        ]),

        'subtotal' => $price,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => $price,

        'payment_method' => 'whatsapp',

        'status' => 'pending',

        'created_at'=>now(),
        'updated_at'=>now()

    ]);

    // Insert Sale Item
    \DB::table('sale_items')->insert([

        'sale_id' => $saleId,

        'product_id' => $product->id,

        'variant_combination_id' => $variant->id,

        'product_name' => $product->name,

        'variant_name' => '',

        'price' => $price,

        'quantity' => 1,

        'total' => $price,

        'created_at'=>now(),
        'updated_at'=>now()

    ]);

    // Generate payment link
    $phoneNumber = preg_replace('/^91/', '', $phone);

    $payment = app(CartController::class)
        ->createPaymentLink(new Request([
            'amount'=>$price,
            'name'=>'WhatsApp Customer',
            'phone'=>$phoneNumber
        ]));

    $dataPayment = $payment->getData(true);

    if($dataPayment['success'])
    {

        $messenger->send($phone,
            "🧾 *Order Created*\n\n".
            "Product : ".$product->name."\n".
            "Amount : ₹".$price."\n\n".
            "Pay Here:\n".$dataPayment['payment_link']
        );

    }else{

        $messenger->send($phone,"Payment link creation failed.");

    }

    // Reset chatbot session
    $session->update([
        'step'=>'start',
        'data'=>null
    ]);

}


}
