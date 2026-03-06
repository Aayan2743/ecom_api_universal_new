<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ChatSession;
use App\Services\Messenger360Service;
use Illuminate\Support\Facades\Validator;


class WhatsAppController extends Controller
{

 protected $messenger;

    public function __construct(Messenger360Service $messenger)
    {
        $this->messenger = $messenger;
    }

    public function webhookss(Request $request)
{

    // \Log::info($request->all());

    $phone = $request->input('phone');
    $message = strtolower(trim($request->input('message')));

    if(!$phone){
        return response()->json(['status'=>'phone missing']);
    }

    if($message == "hi" || $message == "hello")
    {
        $reply = "Welcome 👋\n\n".
                 "1️⃣ View Product\n".
                 "2️⃣ Talk to Support";

        app(Messenger360Service::class)
            ->send($phone,$reply);
    }

    if($message == "1")
    {
        $reply =
        "*Nike Shoes*\n\n".
        "Price ₹1200\n\n".
        "1️⃣ Interested\n".
        "2️⃣ Not Interested";

        app(Messenger360Service::class)
            ->send($phone,$reply);
    }

    if($message == "2")
    {
        app(Messenger360Service::class)
            ->send($phone,"Our team will contact you soon 👍");
    }

    return response()->json([
        "status"=>"received"
    ]);
}

public function webhook(Request $request, Messenger360Service $messenger)
{
    \Log::info('Webhook Data:', $request->all());

    $phone = $request->input('From');

    $message = $request->input('message') ?? $request->input('Chat');
    $message = strtolower(trim($message));

    if(!$phone){
        return response()->json(['status'=>'phone missing']);
    }

    // Step 1: Start
    if($message == "hi")
    {
        $product = \DB::table('products')->first();

        $text =
        "*".$product->name."*\n\n".
        "1️⃣ Interested\n".
        "2️⃣ Not Interested";

        $messenger->send($phone,$text);
    }

    // Step 2: Interested
    elseif($message == "1")
    {
        $product = \DB::table('products')->first();

        $price = 1200; // replace with real price

        $messenger->send($phone,
        "*".$product->name."*\n\n".
        "Price ₹".$price."\n\n".
        "Reply YES to confirm order");
    }

    // Step 3: Confirm Order
elseif($message == "yes")
{
    \Log::info("YES STEP TRIGGERED");

    $price = 1200;

    $phoneNumber = preg_replace('/^91/', '', $phone);

    $payment = app(\App\Http\Controllers\CartController::class)
        ->createPaymentLink(new Request([
            'amount' => $price,
            'name'   => 'WhatsApp Customer',
            'phone'  => $phoneNumber
        ]));

    $data = $payment->getData(true);

    if($data['success'])
    {
        $messenger->send($phone,
            "Order created ✅\n\n".
            "Pay here:\n".$data['payment_link']
        );
    }
    else
    {
        $messenger->send($phone,"Payment link creation failed.");
    }
}

    return response()->json(["status"=>"ok"]);
}

       public function webhooks(Request $request)
    {


      $validator = Validator::make($request->all(), [
            'phone'        => 'required|digits:10',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $phone = $request->phone;
        $message = strtolower(trim($request->message));

        $session = ChatSession::firstOrCreate(
            ['phone' => $phone],
            ['step' => 'start']
        );


        switch ($session->step) {

            case 'start':
                $this->sendProduct($phone, $session);
                break;

            case 'product_sent':

                $this->handleInterest($phone, $message, $session);
                break;

            case 'variant_select':
                $this->handleVariantSelection($phone, $message, $session);
                break;

            case 'confirm_order':
                $this->handleOrderConfirmation($phone, $message, $session);
                break;

        }

    }

    /*
    =========================
    SEND PRODUCT
    =========================
    */

    public function sendProduct($phone, $session)
    {


        $product = DB::table('products')
            ->where('status', 'published')
            ->first();

        if (!$product) {
            $this->messenger->send($phone, "No product available.");
            return;
        }

        $text = "*".$product->name."*\n".
                $product->description."\n\n".
                "1️⃣ Interested\n".
                "2️⃣ Not Interested";

        $this->messenger->send($phone, $text);

        $session->update([
            'step' => 'product_sent',
            'data' => json_encode([
                'product_id' => $product->id
            ])
        ]);

    }

    /*
    =========================
    HANDLE INTEREST
    =========================
    */

    public function handleInterest($phone, $message, $session)
    {


        $data = json_decode($session->data, associative: true);
        $productId = $data['product_id'];
                dd($message);

        if ($message == '1') {

            $variants = DB::table('product_variant_combinations')
                ->where('product_id', $productId)
                ->get();

            if ($variants->isEmpty()) {

                $this->messenger->send($phone,"No variants available.");
                return;

            }

            $text = "Choose Variant\n";

            foreach ($variants as $k => $variant) {

                $text .= ($k+1)." SKU: ".$variant->sku."\n";

            }

            $this->messenger->send($phone,$text);

            $session->update([
                'step' => 'variant_select'
            ]);

        }

        if ($message == '2') {

            $this->messenger->send($phone,"Thank you 👍");

        }

    }

    /*
    =========================
    HANDLE VARIANT SELECT
    =========================
    */

    public function handleVariantSelection($phone, $message, $session)
    {

        $data = json_decode($session->data, true);
        $productId = $data['product_id'];

        $variants = DB::table('product_variant_combinations')
            ->where('product_id', $productId)
            ->get();

        $index = intval($message) - 1;

        if (!isset($variants[$index])) {

            $this->messenger->send($phone,"Invalid option");

            return;

        }

        $variant = $variants[$index];

        $session->update([
            'step' => 'confirm_order',
            'data' => json_encode([
                'product_id'=>$productId,
                'variant_id'=>$variant->id
            ])
        ]);

        $this->messenger->send($phone,
            "Confirm Order?\n".
            "1️⃣ Yes\n".
            "2️⃣ No"
        );

    }

    /*
    =========================
    HANDLE ORDER CONFIRM
    =========================
    */

    public function handleOrderConfirmation($phone,$message,$session)
    {

        if ($message != '1') {

            $this->messenger->send($phone,"Order cancelled");

            return;

        }

        $data = json_decode($session->data,true);

        $productId = $data['product_id'];
        $variantId = $data['variant_id'];

        $product = DB::table('products')->where('id',$productId)->first();
        $variant = DB::table('product_variant_combinations')->where('id',$variantId)->first();

        $price = $variant->purchase_price + $variant->extra_price;

        /*
        CREATE SALE
        */

        $saleId = DB::table('sales')->insertGetId([

            'invoice_number' => 'INV'.rand(10000,99999),
            'customer_phone' => $phone,
            'subtotal' => $price,
            'grand_total' => $price,
            'status' => 'pending',
            'created_at' => now()

        ]);

        /*
        SALE ITEMS
        */

        DB::table('sale_items')->insert([

            'sale_id' => $saleId,
            'product_id' => $productId,
            'variant_combination_id' => $variantId,
            'product_name' => $product->name,
            'sku' => $variant->sku,
            'price' => $price,
            'quantity' => 1,
            'total' => $price

        ]);

        $paymentLink = $this->createPaymentLink($saleId);

        $this->messenger->send($phone,
            "Order Created ✅\n\n".
            "Pay Here:\n".$paymentLink
        );

        $session->update([
            'step'=>'payment_pending'
        ]);

    }

    /*
    =========================
    CREATE PAYMENT LINK
    =========================
    */

    public function createPaymentLink($saleId)
    {

        return url('/payment/'.$saleId);

    }

    /*
    =========================
    SEND WHATSAPP MESSAGE
    =========================
    */


}
