<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Category;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ChatSession;
use App\Models\Product;
use App\Models\ProductVariantCombination;
use App\Models\User;
use App\Services\Messenger360Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use App\Models\PaymentLink;


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

    // Get phone from webhook
    $phone = $request->input('From');

    // Normalize phone number (remove 91)
    if(strlen($phone) == 12 && substr($phone,0,2) == '91'){
        $phone = substr($phone,2);
    }

    // $message = strtolower(trim(
    //     $request->input('message')
    //     ?? $request->input('Chat')
    //     ?? $request->input('text')
    // ));


     // ✅ FIX STARTS HERE
    $message = $request->input('message');

    if($message === null){
        $message = $request->input('Chat');
    }

    if($message === null){
        $message = $request->input('text');
    }

    $message = strtolower(trim((string)$message));

    if($message === ''){
        return response()->json(['status'=>'message missing']);
    }
    // ✅ FIX ENDS HERE

    if(!$phone){
        return response()->json(['status'=>'phone missing']);
    }

    if(!$message){
        return response()->json(['status'=>'message missing']);
    }

    // Continue your session logic
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



            case 'confirm_address':
        return $this->confirmAddress($phone,$message,$session,$messenger);

         case 'address':
        return $this->saveAddress($phone,$message,$session,$messenger);

         case 'city':
        return $this->saveCity($phone,$message,$session,$messenger);

    case 'state':
        return $this->saveState($phone,$message,$session,$messenger);

    case 'pincode':
        return $this->savePincode($phone,$message,$session,$messenger);

        case 'select_address':
    return $this->selectAddress($phone,$message,$session,$messenger);

        case 'payment_pending':

            Log::info('User message during payment pending',[
                'phone'=>$phone,
                'message'=>$message
            ]);

            $messenger->send(
                $phone,
                "⏳ Your order is awaiting payment.\n\nPlease complete payment using the link sent earlier."
            );

            return response()->json(['status'=>'waiting_payment']);



              default:
        Log::warning('Unknown step, resetting session');

        $session->update([
            'step'=>'start',
            'data'=>[]
        ]);

        return $this->sendCategories($phone,$session,$messenger);
    }
}

private function sendCategories($phone,$session,$messenger)
{

    Log::info('sendCategories triggered',[
        'phone'=>$phone
    ]);

    $categories = Category::where('is_active',1)
        ->orderBy('sort_order')
        ->get();

    if($categories->isEmpty()){
        Log::warning('No categories found');
        $messenger->send($phone,"❌ No categories available.");
        return;
    }

    $text  = "🛍 *Select Category*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($categories as $key=>$cat){

        $number = $key + 1;

        $text .= $number.". ".$cat->name."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "👉 Reply with category number";

    Log::info('Sending category menu',[
        'category_count'=>$categories->count()
    ]);

    $messenger->send($phone,$text);

    // reset session data when returning to categories
    $session->update([
        'step'=>'category',
        'data'=>[]
    ]);

    Log::info('Session updated to category step');
}



private function selectCategory($phone,$message,$session,$messenger)
{

    Log::info('selectCategory triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    // decode session data
    $data = is_array($session->data) ? $session->data : json_decode($session->data, true);
    $data = $data ?? [];

    // Back to categories
    if($message == '0'){
        Log::info('User requested back to categories');
        return $this->sendCategories($phone,$session,$messenger);
    }

    if(!is_numeric($message)){
        Log::warning('User sent non-numeric category');
        $messenger->send($phone,"❌ Please reply with the category number.");
        return;
    }

    $categories = Category::where('is_active',1)
        ->orderBy('sort_order')
        ->get();

    $index = (int)$message - 1;

    if(!isset($categories[$index])){
        Log::warning('Invalid category selected',[
            'message'=>$message
        ]);

        $messenger->send($phone,"❌ Invalid option. Please select again.");
        return;
    }

    $category = $categories[$index];

    Log::info('Category selected',[
        'category_id'=>$category->id,
        'category_name'=>$category->name
    ]);

    // pagination
    $page  = $data['page'] ?? 1;
    $limit = 5;

    $products = Product::where('category_id',$category->id)
        ->skip(($page-1)*$limit)
        ->take($limit)
        ->get();

    if($products->isEmpty()){
        $messenger->send($phone,"No products available.");
        return;
    }

    // message
    $text = "🛍 *Products in ".$category->name."*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($products as $key => $product){
        $number = $key + 1;
        $text .= $number.". ".$product->name."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "👉 Reply product number\n\n";
    $text .= "9️⃣ More Products\n";
    $text .= "b Back to Categories";

    $messenger->send($phone,$text);

    // save session
    $data['category_id'] = $category->id;
    $data['page'] = $page;

    $session->update([
        'step'=>'product',
        'data'=>$data
    ]);

    Log::info('Session moved to product step',[
        'page'=>$page
    ]);
}



private function selectProduct($phone,$message,$session,$messenger)
{


    Log::info('selectProduct triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    $data = $session->data ?? [];

    // BACK TO CATEGORY



if($message == '0' || $message == 'back' || $message == 'b'){
    Log::info('User requested back to categories');

    $session->update([
        'step'=>'category',
        'data'=>[]
    ]);

    return $this->sendCategories($phone,$session,$messenger);
}

    // MORE PRODUCTS
    if($message == '9'){

        $data['page'] = ($data['page'] ?? 1) + 1;

        $session->update([
            'data'=>$data
        ]);

        return $this->showProducts($phone,$session,$messenger);
    }

    if(!is_numeric($message)){
        $messenger->send($phone,"❌ Invalid option.");
        return;
    }

    $page  = $data['page'] ?? 1;
    $limit = 5;

    $products = Product::where('category_id',$data['category_id'])
        ->skip(($page-1)*$limit)
        ->take($limit)
        ->get();

    $index = (int)$message - 1;

    if(!isset($products[$index])){
        $messenger->send($phone,"❌ Invalid product.");
        return;
    }

    $product = $products[$index];

    $data['product_id'] = $product->id;

    $session->update([
        'step'=>'variant',
        'data'=>$data
    ]);

    return $this->showVariants($phone,$session,$messenger);
}


private function showVariants($phone,$session,$messenger)
{
    Log::info('showVariants triggered',[
        'phone'=>$phone
    ]);

    $data = $session->data ?? [];

    // If product missing → go back to product list
    if(empty($data['product_id'])){
        Log::warning('Product ID missing in session');
        return $this->showProducts($phone,$session,$messenger);
    }

    $product = Product::with('variantCombinations.values')
        ->find($data['product_id']);

    if(!$product){
        Log::warning('Product not found',[
            'product_id'=>$data['product_id']
        ]);

        return $this->showProducts($phone,$session,$messenger);
    }

    $variants = $product->variantCombinations;

    if($variants->isEmpty()){
        $messenger->send($phone,"❌ No variants available.");
        return;
    }

    $text  = "📦 *Select Variant*\n";
    $text .= "*".$product->name."*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($variants as $key=>$variant){

        $number = $key + 1;

        // Get variant name
        $variationName = $variant->values
            ->pluck('value')
            ->implode(' / ');

        if(!$variationName){
            $variationName = 'Default';
        }

        // Get price
        $price = $variant->amount ?? $variant->extra_price ?? 0;

        $text .= $number.". ".$variationName." - ₹".$price."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "👉 Reply variant number\n\n";
    $text .= "b. Back to Products";

    $messenger->send($phone,$text);

    // Keep session at variant step
    $session->update([
        'step'=>'variant',
        'data'=>$data
    ]);

    Log::info('Session moved to variant step');
}


private function selectVariant($phone,$message,$session,$messenger)
{
    Log::info('selectVariant triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    $data = $session->data ?? [];

    // Back to products
    if($message == 'b'){
        return $this->showProducts($phone,$session,$messenger);
    }

    if(!is_numeric($message)){
        $messenger->send($phone,"❌ Invalid option.");
        return;
    }

    $product = Product::with('variantCombinations.values')
        ->find($data['product_id']);

    $variants = $product->variantCombinations;

    $index = (int)$message - 1;

    if(!isset($variants[$index])){
        $messenger->send($phone,"❌ Invalid variant.");
        return;
    }

    $variant = $variants[$index];

    $data['variant_id'] = $variant->id;

    // 🔹 Check if user exists
  Log::info('Checking user and address',[
    'phone' => $phone
]);

$user = User::where('phone',$phone)->with('addresses')->first();

if($user){
    Log::info('User found',[
        'user_id' => $user->id,
        'address_count' => $user->addresses->count()
    ]);
}else{
    Log::warning('User not found',[
        'phone' => $phone
    ]);
}



if($user && $user->addresses && $user->addresses->count() > 0){

    Log::info('User has addresses',[
        'user_id' => $user->id,
        'address_count' => $user->addresses->count()
    ]);

    $text  = "📍 *Select Delivery Address*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($user->addresses as $key => $addr){

        $number = $key + 1;

        Log::info('Address option',[
            'address_id' => $addr->id,
            'address' => $addr->address
        ]);

        $text .= $number.". ".$addr->address;

        if($addr->city){
            $text .= ", ".$addr->city;
        }

        if($addr->pincode){
            $text .= " - ".$addr->pincode;
        }

        $text .= "\n\n";
    }

    $newAddressOption = $user->addresses->count() + 1;

    $text .= "━━━━━━━━━━━━━━\n";
    $text .= $newAddressOption.". Add New Address";

    $session->update([
        'step'=>'select_address',
        'data'=>$data
    ]);

    Log::info('Session moved to select_address step');

    $messenger->send($phone,$text);
}
else{

    Log::warning('No address found for user, asking for new address',[
        'phone'=>$phone
    ]);

    $session->update([
        'step'=>'address',
        'data'=>$data
    ]);

    $messenger->send(
        $phone,
        "📍 Please enter your delivery address"
    );
}
}

private function showProducts($phone,$session,$messenger)
{
    $data = $session->data ?? [];

    if(empty($data['category_id'])){
        return $this->sendCategories($phone,$session,$messenger);
    }

    $category = Category::find($data['category_id']);

    if(!$category){
        return $this->sendCategories($phone,$session,$messenger);
    }

    $page  = $data['page'] ?? 1;
    $limit = 5;

    $products = Product::where('category_id',$category->id)
        ->skip(($page-1)*$limit)
        ->take($limit)
        ->get();

    if($products->isEmpty()){
        $messenger->send($phone,"❌ No more products available.");
        return;
    }

    $text = "🛍 *Products in ".$category->name."*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($products as $key=>$product){
        $number = $key + 1;
        $text .= $number.". ".$product->name."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "👉 Reply product number\n\n";
    $text .= "9️⃣ More Products\n";
    $text .= "b Back to Categories";

    $messenger->send($phone,$text);

    // ensure session stays in product step
    $session->update([
        'step'=>'product',
        'data'=>$data
    ]);
}

private function confirmAddress($phone,$message,$session,$messenger)
{
    Log::info('confirmAddress triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    $data = $session->data ?? [];

    if($message == '1'){

        $user = User::where('phone',$phone)->with('addresses')->first();

        $address = $user->addresses->first();

        $data['address']  = $address->address;
        $data['city']     = $address->city;
        $data['state']    = $address->state;
        $data['pincode']  = $address->pincode;

        $session->update([
            'step'=>'payment',
            'data'=>$data
        ]);

        $messenger->send($phone,"✅ Using saved address.");
    }

    elseif($message == '2'){

        $session->update([
            'step'=>'address',
            'data'=>$data
        ]);

        $messenger->send(
            $phone,
            "📍 Please enter your *Street Address*\n\nExample:\n12 Main Road, Near Temple"
        );
    }
}



private function saveAddress($phone,$message,$session,$messenger)
{
    Log::info('saveAddress triggered');

    $data = $session->data ?? [];

    $data['address'] = $message;

    $session->update([
        'step'=>'city',
        'data'=>$data
    ]);

    $messenger->send($phone,"🏙 Please enter your *City*");
}


private function saveCity($phone,$message,$session,$messenger)
{
    Log::info('saveCity triggered');

    $data = $session->data ?? [];

    $data['city'] = $message;

    $session->update([
        'step'=>'state',
        'data'=>$data
    ]);

    $messenger->send($phone,"🗺 Please enter your *State*");
}

private function saveState($phone,$message,$session,$messenger)
{
    Log::info('saveState triggered');

    $data = $session->data ?? [];

    $data['state'] = $message;

    $session->update([
        'step'=>'pincode',
        'data'=>$data
    ]);

    $messenger->send($phone,"📮 Please enter your *Pincode*");
}

private function savePincode($phone,$message,$session,$messenger)
{
    Log::info('savePincode triggered');

    $data = $session->data ?? [];

    $data['pincode'] = $message;

    $user = User::where('phone',$phone)->first();

    Address::create([
        'user_id'   => $user->id,
        'name'      => $user->name ?? 'Customer',
        'phone'     => $phone,
        'address'   => $data['address'],
        'city'      => $data['city'],
        'state'     => $data['state'],
        'country'   => 'India',
        'pincode'   => $data['pincode'],
        'is_default'=> 1
    ]);

    Log::info('Address saved');

    $session->update([
        'step'=>'payment',
        'data'=>[]
    ]);

    $messenger->send(
        $phone,
        "✅ Address saved successfully.\n\nPreparing your order..."
    );
}


private function selectAddress($phone,$message,$session,$messenger)
{
    Log::info('selectAddress triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    $data = $session->data ?? [];

    if(!is_numeric($message)){
        $messenger->send($phone,"❌ Please reply with the address number.");
        return;
    }

    $user = User::where('phone',$phone)->with('addresses')->first();

    $addresses = $user->addresses;

    $index = (int)$message - 1;

    // Add new address option
    if($index == $addresses->count()){

        $session->update([
            'step'=>'address',
            'data'=>$data
        ]);

        $messenger->send($phone,"📍 Please enter your Street Address");
        return;
    }

    if(!isset($addresses[$index])){
        $messenger->send($phone,"❌ Invalid address selection.");
        return;
    }

    $address = $addresses[$index];

    $data['address'] = $address->address;
    $data['city'] = $address->city;
    $data['state'] = $address->state;
    $data['pincode'] = $address->pincode;

    $session->update([
        'step'=>'payment',
        'data'=>$data
    ]);

    $messenger->send(
        $phone,
        "✅ Address selected.\n\nPreparing your order..."
    );

    $this->sendPaymentLink($phone,$session,$messenger);
}


private function sendPaymentLink_q($phone,$session,$messenger)
{
    Log::info('Creating payment link',[
        'phone'=>$phone
    ]);

    $data = $session->data ?? [];

    $variant = ProductVariantCombination::find($data['variant_id']);

    if(!$variant){
        Log::error('Variant not found');
        return;
    }

    $amount = $variant->amount; // your variant price

    $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

    $payment = $api->paymentLink->create([
        'amount' => $amount * 100, // paise
        'currency' => 'INR',
        'description' => 'Order Payment',
        'customer' => [
            'contact' => $phone
        ],
        'notify' => [
            'sms' => false,
            'email' => false
        ],
        'callback_url' => url('/payment-success'),
        'callback_method' => 'get'
    ]);

    $paymentLink = $payment['short_url'];

    Log::info('Payment link created',[
        'link'=>$paymentLink
    ]);

    $messenger->send(
        $phone,
        "💳 *Payment Link*\n\nClick below to complete your order:\n".$paymentLink
    );

    $session->update([
        'step'=>'payment_pending'
    ]);
}

private function sendPaymentLink($phone,$session,$messenger)
{
    Log::info('Creating payment link',[
        'phone'=>$phone
    ]);

    $data = $session->data ?? [];

    $variant = ProductVariantCombination::with('product')
                ->find($data['variant_id']);

    if(!$variant){
        Log::error('Variant not found');
        return;
    }

    $amount = $variant->amount;

    $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

    $payment = $api->paymentLink->create([
        'amount' => $amount * 100,
        'currency' => 'INR',
        'description' => 'Order Payment',
        'customer' => [
            'name' => $variant->product->name ?? 'Customer',
            'contact' => $phone
        ],
        'notify' => [
            'sms' => false,
            'email' => false
        ],
        'callback_url' => url('/payment-success'),
        'callback_method' => 'get'
    ]);

    $paymentLink = $payment['short_url'];

    Log::info('Payment link created',[
        'link'=>$paymentLink
    ]);

    /*
    SAVE PAYMENT LINK DETAILS
    */
    PaymentLink::create([
        'razorpay_link_id' => $payment['id'],
        'payment_link'     => $paymentLink,
        'amount'           => $amount,
        'customer_name'    => $variant->product->name ?? 'Customer',
        'customer_phone'   => $phone,
        'status'           => 'pending'
    ]);

    // $messenger->send(
    //     $phone,
    //     "💳 *Payment Link*\n\nClick below to complete your order:\n".$paymentLink
    // );

    $confirmUrl = url('/payment-success/'.$payment['id']);

    $messenger->send(
        $phone,
        "💳 *Payment Link*\n\n".
        "Complete payment using the link below:\n".
        $payment['short_url']."\n\n".
        "After payment click below to confirm:\n".
        $confirmUrl
    );

    $session->update([
        'step'=>'payment_pending'
    ]);
}

  public function checkPayment($linkId)
    {
        $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

        try {

            $link = $api->paymentLink->fetch($linkId);

            if($link['status'] == 'paid'){

                PaymentLink::where('razorpay_link_id',$linkId)
                    ->update([
                        'status'=>'paid',
                        'paid_at'=>now()
                    ]);

                return response()->json([
                    'status'=>'success',
                    'message'=>'Payment completed'
                ]);

            }

            return response()->json([
                'status'=>'pending',
                'message'=>'Payment not completed'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ]);
        }
    }


}
