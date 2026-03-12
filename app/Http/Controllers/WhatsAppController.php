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
use App\Services\Messenger360Service1;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use App\Models\PaymentLink;


class WhatsAppController extends Controller
{

 protected $messenger;

    public function __construct(Messenger360Service1 $messenger)
    {
        $this->messenger = $messenger;
    }


    public function deleteAllChatSessions()
{
    ChatSession::truncate();

    return response()->json([
        'success' => true,
        'message' => 'All chat sessions deleted successfully'
    ]);
}



public function webhook(Request $request, Messenger360Service1 $messenger)
{
    Log::info('Webhook Data:', $request->all());

    // Get phone from webhook
    $phone = $request->input('From');

    // Normalize phone number (remove 91)
    if(strlen($phone) == 12 && substr($phone,0,2) == '91'){
        $phone = substr($phone,2);
    }




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

      $user = User::where('phone',$phone)->first();

//  dd($user);
   if(!$user && $session->step == 'start')
{
    $imageUrl = asset('images/no-product.jpg');

    $text = "👋 Welcome! To Sri Devi Herbals

        Please enter your name to continue.";

    $messenger->send(
        $phone,
        $text,
        $imageUrl
    );

    $session->update([
        'step'=>'ask_name'
    ]);

    return response()->json(['status'=>'ask_name']);
}




    switch($session->step)
    {

         case 'ask_name':
            return $this->saveUserName($phone,$message,$session,$messenger);

        case 'start':
            // return $this->sendCategories($phone,$session,$messenger);
            return $this->showMainMenu($phone,$user,$session,$messenger);

        case 'menu':
            return $this->handleMenu($phone,$message,$session,$messenger);

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


            case 'order_summary':
    return $this->handleOrderSummary($phone,$message,$session,$messenger);

    case 'confirm_order':
    return $this->handleOrderConfirmation($phone,$message,$session,$messenger);



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
        $messenger->send($phone,"❌ No categories available.");
        return;
    }

    $text  = "🛍 *Select a Category*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($categories as $key=>$cat){

        $number = $key + 1;

        $text .= "*".$number.".* ".$cat->name."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "↩️ *B* - Back\n\n";
    $text .= "👉 Reply with the category number";

    $messenger->send($phone,$text);

    $session->update([
        'step'=>'category',
        'data'=>[]
    ]);
}


private function handleOrderConfirmation($phone,$message,$session,$messenger)
{
    if($message == '1'){
        return $this->sendPaymentLink($phone,$session,$messenger);
    }

    if($message == '2'){
        $session->update([
            'step'=>'address'
        ]);

        $messenger->send(
            $phone,
            "📍 Please enter new delivery address"
        );
        return;
    }

    if($message == '3'){

        $session->update([
            'step'=>'start',
            'data'=>[]
        ]);

        $messenger->send(
            $phone,
            "❌ Order cancelled.\n\nReturning to main menu..."
        );

        $user = User::where('phone',$phone)->first();

        return $this->showMainMenu($phone,$user,$session,$messenger);
    }

    $messenger->send(
        $phone,
        "❌ Invalid option.\n\n1️⃣ Confirm Order\n2️⃣ Change Address\n3️⃣ Cancel"
    );
}


public function showMainMenu($phone,$user,$session,$messenger)
{


    $text = "👋 Welcome! To Sri Devi Herbals {$user->name}

🏪 *Our Branches*

📍 *Branch 1*
Balajinagar Road No 1
Peerzadiguda, Boduppal
Hyderabad - 500098

📍 Map:
https://maps.app.goo.gl/KjZ1pF13v7Nd5qWTA?g_st=aw


📍 *Branch 2*
PVT Market, Shop No 23B (-1 Floor)
Kothapet, Chaitanyapuri Metro
Dilsukhnagar, Hyderabad - 500035

📍 Map:
https://maps.app.goo.gl/Q6FhGkhSMkRrL5FS9?g_st=aw


How can we help you today?

1. Shopping
2. Tracking";

   $imageUrl = asset('images/no-product.jpg');
    $messenger->send($phone,$text, $imageUrl);

    $session->update([
        'step'=>'menu'
    ]);

    return response()->json(['status'=>'menu']);
}


private function saveUserName($phone,$message,$session,$messenger)
{
    $user = User::create([
        'name'=>$message,
        'phone'=>$phone,
        'role'=>'user',
        'password'=>bcrypt('123456')
    ]);

    $text = "✅ Thank you $message!

🏪 *Our Branches*

📍 *Branch 1*
Balajinagar Road No 1
Peerzadiguda, Boduppal
Hyderabad - 500098

📍 Map:
https://maps.app.goo.gl/KjZ1pF13v7Nd5qWTA?g_st=aw


📍 *Branch 2*
PVT Market, Shop No 23B (-1 Floor)
Kothapet, Chaitanyapuri Metro
Dilsukhnagar, Hyderabad - 500035

📍 Map:
https://maps.app.goo.gl/Q6FhGkhSMkRrL5FS9?g_st=aw


How can we help you today?

*1.* Shopping
*2.* Track Order

";

     $imageUrl = asset('images/no-product.jpg');

    $messenger->send($phone,$text);

    $session->update([
        'step'=>'menu'
    ]);

    return response()->json(['status'=>'name_saved']);
}

public function handleMenu($phone,$message,$session,$messenger)
{
    if($message == '1'){
        $session->update(['step'=>'category']);
        return $this->sendCategories($phone,$session,$messenger);
    }

    if($message == '2'){
        $messenger->send($phone,"📦 Please enter your order ID to track.");
        return response()->json(['status'=>'tracking']);
    }

    $messenger->send($phone,"❌ Invalid option\n\n1️⃣ Categories\n2️⃣ Track Order");
}



private function selectCategory($phone,$message,$session,$messenger)
{

    Log::info('selectCategory triggered',[
        'phone'=>$phone,
        'message'=>$message
    ]);

    $data = is_array($session->data) ? $session->data : json_decode($session->data, true);
    $data = $data ?? [];

    // ✅ Back to menu


        if($message == 'b'){

            Log::info('User requested back to main menu');

            $user = User::where('phone',$phone)->first();

            return $this->showMainMenu($phone,$user,$session,$messenger);
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
        $messenger->send($phone,"❌ Invalid option. Please select again.");
        return;
    }

    $category = $categories[$index];

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

    $text = "🛍 *Products in ".$category->name."*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    foreach($products as $key => $product){
        $number = $key + 1;
        $text .= $number.". ".$product->name."\n";
    }

    $text .= "\n━━━━━━━━━━━━━━\n";
    $text .= "👉 Reply product number\n\n";
    $text .= "9️⃣ More Products\n";
    $text .= "b Back";

    $messenger->send($phone,$text);

    $data['category_id'] = $category->id;
    $data['page'] = $page;

    $session->update([
        'step'=>'product',
        'data'=>$data
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

    // Product missing
    if(empty($data['product_id'])){
        Log::warning('Product ID missing in session');
        return $this->showProducts($phone,$session,$messenger);
    }

    // Load product
    $product = Product::with(['variantCombinations.values','images'])
        ->find($data['product_id']);

    if(!$product){
        Log::warning('Product not found',[
            'product_id'=>$data['product_id']
        ]);
        return $this->showProducts($phone,$session,$messenger);
    }

    // Default image
    $imageUrl = asset('images/no-product.jpg');

    // Get primary image
    $primaryImage = $product->images->where('is_primary',1)->first();

    if(!$primaryImage){
        $primaryImage = $product->images->first();
    }

    if($primaryImage && !empty($primaryImage->image_path)){

        $imagePath = $primaryImage->image_path;

        // Convert WEBP → JPG if needed
        if(str_ends_with($imagePath,'.webp')){
            $imagePath = $this->convertWebpToJpg($imagePath);
        }

        // $imageUrl = asset($imagePath);
        $imageUrl = asset('storage/'.$imagePath);
    }

    // Get variants
    $variants = $product->variantCombinations;

    if($variants->isEmpty()){
        $messenger->send($phone,"❌ No variants available.");
        return;
    }

    // Build message
    $text  = "📦 *Select Variant*\n";
$text .= "*".$product->name."*\n";
$text .= "━━━━━━━━━━━━━━\n\n";

foreach($variants as $key=>$variant){

    $number = $key + 1;

    $variationName = $variant->values
        ->pluck('value')
        ->implode(' / ');

    if(!$variationName){
        $variationName = 'Default';
    }

    $price = $variant->amount ?? $variant->extra_price ?? 0;

    $text .= $number.". ".$variationName." - ₹".$price."\n";
}

$text .= "\n━━━━━━━━━━━━━━\n";

$text .= "🔗 *Product Details*\n";
$text .= "Description: https://www.herbalandco.in/product/indigo?srsltid=AfmBOorigxZ0KGENRpZXRIFAyICja-EiyPKfEOifIqQ3z9P7Up0JVyO8.\n";
$text .= "🎥 Video: https://www.youtube.com/shorts/0BRG6MPKGaQ \n\n";

$text .= "👉 Reply variant number\n\n";
$text .= "b. Back to Products";

    Log::info('Sending image with variants',[
        'image'=>$imageUrl
    ]);

    // ✅ Send IMAGE + TEXT together
    $messenger->send(
        $phone,
        $text,
        $imageUrl
    );

    // Update session
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

    if(!$product){
        $messenger->send($phone,"❌ Product not found.");
        return;
    }

    $variants = $product->variantCombinations;

    $index = (int)$message - 1;

    if(!isset($variants[$index])){
        $messenger->send($phone,"❌ Invalid variant.");
        return;
    }

    $variant = $variants[$index];

    // Save variant
    $data['variant_id'] = $variant->id;

    // Get variant name
    $variationName = $variant->values
        ->pluck('value')
        ->implode(' / ');

    if(!$variationName){
        $variationName = 'Default';
    }

    $price = $variant->amount ?? $variant->extra_price ?? 0;

    // 🧾 ORDER SUMMARY MESSAGE
    $text  = "🧾 *Order Summary*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";
    $text .= "Product: ".$product->name."\n";
    $text .= "Variant: ".$variationName."\n";
    $text .= "Price: ₹".$price."\n\n";
    $text .= "━━━━━━━━━━━━━━\n";
    $text .= "1️⃣ Continue\n";
    $text .= "2️⃣ Change Variant\n";
    $text .= "3️⃣ Cancel";

    $session->update([
        'step'=>'order_summary',
        'data'=>$data
    ]);

    $messenger->send($phone,$text);
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

        $user = User::where('phone',$phone)->first();

        // 🔥 SAVE ADDRESS
        $address = Address::create([
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

        Log::info('Address saved',[
            'address_id'=>$address->id
        ]);

        $data['address_id'] = $address->id;

        $session->update([
            'step'=>'payment_pending',
            'data'=>$data
        ]);

        $messenger->send(
            $phone,
            "✅ Address saved successfully.\n\n⏳ Preparing your payment link..."
        );

        return $this->sendPaymentLink($phone,$session,$messenger);
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

    else{
        $messenger->send(
            $phone,
            "❌ Invalid option.\n\n1️⃣ Confirm Address\n2️⃣ Change Address"
        );
    }
}

private function handleOrderSummary($phone,$message,$session,$messenger)
{
    $data = $session->data ?? [];

    // Continue to address
    if($message == '1'){

        $user = User::where('phone',$phone)->with('addresses')->first();

        if($user && $user->addresses->count() > 0){

            $text  = "📍 *Select Delivery Address*\n";
            $text .= "━━━━━━━━━━━━━━\n\n";

            foreach($user->addresses as $key=>$addr){

                $number = $key + 1;

                $text .= $number.". ".$addr->address;

                if($addr->city){
                    $text .= ", ".$addr->city;
                }

                if($addr->pincode){
                    $text .= " - ".$addr->pincode;
                }

                $text .= "\n\n";
            }

            $text .= "━━━━━━━━━━━━━━\n";
            $text .= ($user->addresses->count()+1).". Add New Address";

            $session->update([
                'step'=>'select_address',
                'data'=>$data
            ]);

            $messenger->send($phone,$text);
        }
        else{

            $session->update([
                'step'=>'address',
                'data'=>$data
            ]);

            $messenger->send(
                $phone,
                "📍 Please enter your delivery address"
            );
        }

        return;
    }

    // Change variant
    if($message == '2'){
        return $this->showVariants($phone,$session,$messenger);
    }

    // ❌ Cancel order
    if($message == '3'){

        $session->update([
            'step'=>'start',
            'data'=>[]
        ]);

        $user = User::where('phone',$phone)->first();

        $messenger->send(
            $phone,
            "❌ Order cancelled.\n\nReturning to main menu..."
        );

        return $this->showMainMenu($phone,$user,$session,$messenger);
    }

    $messenger->send(
        $phone,
        "❌ Invalid option.\n\n1️⃣ Continue\n2️⃣ Change Variant\n0️⃣ Cancel"
    );
}


private function saveAddress($phone,$message,$session,$messenger)
{
    Log::info('saveAddress triggered');

    $data = $session->data ?? [];

    $data['address'] = $message;

    $session->update([
        'step'=>'pincode',
        'data'=>$data
    ]);

    $messenger->send($phone,"🏙 Please enter your *Pincode* To get All ur City & State");
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

    $pincode = trim($message);

    $apiUrl = "https://api.postalpincode.in/pincode/".$pincode;

    $response = file_get_contents($apiUrl);
    $result = json_decode($response,true);

    if(!$result || $result[0]['Status'] != 'Success'){
        $messenger->send(
            $phone,
            "❌ Invalid pincode. Please enter a valid pincode."
        );
        return;
    }

    $postOffice = $result[0]['PostOffice'][0];

    $data['pincode'] = $postOffice['Pincode'];
    $data['city']    = $postOffice['District'];
    $data['state']   = $postOffice['State'];
    $data['country'] = $postOffice['Country'];

    $text  = "📍 *Confirm Your Address*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    $text .= $data['address']."\n";
    $text .= $data['city']."\n";
    $text .= $data['state']." - ".$data['pincode']."\n";
    $text .= "India\n\n";

    $text .= "━━━━━━━━━━━━━━\n";
    $text .= "1️⃣ Confirm Address\n";
    $text .= "2️⃣ Change Address";

    $session->update([
        'step'=>'confirm_address',
        'data'=>$data
    ]);

    $messenger->send($phone,$text);
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

    $data['address_id'] = $address->id;

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


private function showFinalConfirmation($phone,$session,$messenger)
{
    $data = $session->data ?? [];

    $variant = ProductVariantCombination::with(['product','values'])
        ->find($data['variant_id']);

    $variationName = $variant->values
        ->pluck('value')
        ->implode(' / ');

    $price = $variant->amount ?? 0;

    $text  = "🧾 *Confirm Your Order*\n";
    $text .= "━━━━━━━━━━━━━━\n\n";

    $text .= "Product: ".$variant->product->name."\n";
    $text .= "Variant: ".$variationName."\n";
    $text .= "Price: ₹".$price."\n\n";

    $text .= "📍 Delivery Address\n";
    $text .= $data['address'].", ".$data['city']."\n";
    $text .= $data['state']." - ".$data['pincode']."\n\n";

    $text .= "━━━━━━━━━━━━━━\n";
    $text .= "1️⃣ Confirm Order\n";
    $text .= "2️⃣ Change Address\n";
    $text .= "3️⃣ Cancel";

      // IMPORTANT: set session step
    $session->update([
        'step' => 'confirm_order',
        'data' => $data
    ]);

    $messenger->send($phone,$text);
}


private function sendPaymentLin_w($phone,$session,$messenger)
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

private function sendPaymentLink($phone,$session,$messenger)
{
    Log::info('Creating payment link',['phone'=>$phone]);

    $data = $session->data ?? [];



       Log::info('Session Data',['Session Data'=>$data]);
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
    'callback_url' => url('/api/payment-success'),
    'callback_method' => 'get'
]);

$paymentLink = $payment['short_url'];
$paymentId = $payment['id'];

$confirmUrl = url('api/payment-success/'.$paymentId);

    $paymentLink = $payment['short_url'];

    // save payment snapshot
    PaymentLink::create([
        'razorpay_link_id' => $payment['id'],
        'payment_link'     => $paymentLink,
        'amount'           => $amount,
        'customer_name'    => $variant->product->name ?? 'Customer',
        'customer_phone'   => $phone,
        'variant_id'       => $data['variant_id'],
        'address_id'       => $data['address_id'] ?? null,
        'status'           => 'pending'
    ]);

    $confirmUrl = url('/payment-success/'.$payment['id']);

    $messenger->send(
        $phone,
        "💳 *Payment Link*\n\n".
        "Complete payment:\n".
        $paymentLink."\n\n".
        "After payment click:\n".
        $confirmUrl
    );

    $session->update(['step'=>'payment_pending']);
}





public function checkPayment(Request $request)
{
    $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

    try {

        // Razorpay parameters
        $linkId = $request->razorpay_payment_link_id;
        $paymentId = $request->razorpay_payment_id;
        $status = $request->razorpay_payment_link_status;

        if(!$linkId){
            return response()->json([
                'status'=>'error',
                'message'=>'Payment link id missing'
            ]);
        }

        // fetch payment link from Razorpay
        $link = $api->paymentLink->fetch($linkId);

        if($status == 'paid'){

            $payment = PaymentLink::where('razorpay_link_id',$linkId)->first();

            if(!$payment){
                return response()->json([
                    'status'=>'error',
                    'message'=>'Payment record not found'
                ]);
            }

            // mark payment paid
            $payment->update([
                'status'=>'paid',
                'paid_at'=>now()
            ]);


             // USER
            $user = User::where('phone',$payment->customer_phone)->first();

            // VARIANT
            $variant = ProductVariantCombination::with('product')
                        ->find($payment->variant_id);

                        // dd($variant);

            if(!$variant){
                return response()->json([
                    'status'=>'error',
                    'message'=>'Variant not found'
                ]);
            }

            // /*
            // --------------------------------------------------
            // CREATE ORDER
            // --------------------------------------------------
            // */

            // $orderId = DB::table('orders')->insertGetId([
            //     'merchant_order_id'     => 'ORD'.time(),
            //     'phonepe_transaction_id'=> $paymentId,
            //     'user_id'               => $user->id,
            //     'address_id'            => $payment->address_id,
            //     'payment_method'        => 'razorpay',
            //     'razorpay_order_id'     => $linkId,
            //     'razorpay_payment_id'   => $paymentId,
            //     'subtotal'              => $variant->amount,
            //     'discount'              => 0,
            //     'total_amount'          => $variant->amount,
            //     'status'                => 'paid',
            //     'created_at'            => now(),
            //     'updated_at'            => now()
            // ]);

            // /*
            // --------------------------------------------------
            // ORDER ITEM SNAPSHOT
            // --------------------------------------------------
            // */

            // DB::table('order_items')->insert([
            //     'order_id'   => $orderId,
            //     'product_id' => $variant->product_id,
            //     'quantity'   => 1,
            //     'price'      => $variant->amount,
            //     'total'      => $variant->amount,
            //     'created_at' => now(),
            //     'updated_at' => now()
            // ]);


            $address = Address::find($payment->address_id);



// CREATE SALE
$saleId = DB::table('sales')->insertGetId([
      'invoice_number'            => 'INV-' . now()->format('YmdHis') . '-' . rand(100, 999),
    'customer_id'    => $user->id ?? null,
    'user_id'        => $user->id ?? null,

    'shipping_address_snapshot' => json_encode([
        'name'    => $address->name ?? '',
        'phone'   => $address->phone ?? '',
        'address' => $address->address ?? '',
        'city'    => $address->city ?? '',
        'state'   => $address->state ?? '',
        'pincode' => $address->pincode ?? ''
    ]),

    'subtotal'       => $variant->amount,
    'discount_total' => 0,
    'tax_total'      => 0,
    'grand_total'    => $variant->amount,

    'payment_method' => 'razorpay',
    'paid_amount'    => $variant->amount,
    'change_amount'  => 0,

    'customer_name'  => $user->name ?? 'Customer',
    'customer_phone' => $payment->customer_phone,

    'status' => 'created',
    'order_from' => 'whatsapp',

    'created_at' => now(),
    'updated_at' => now()
]);

// VARIANT NAME
$variantName = $variant->values->pluck('value')->implode(' / ');

// PRODUCT IMAGE
$image = optional($variant->images->first())->image_path;

// CREATE SALE ITEM
DB::table('sale_items')->insert([
    'sale_id' => $saleId,

    'product_id'             => $variant->product_id,
    'variant_combination_id' => $variant->id,

    'product_name' => $variant->product->name,
    'variant_name' => $variantName,
    'sku'          => $variant->sku ?? null,

    'product_image' => $image,

    'price'    => $variant->amount,
    'discount' => 0,
    'tax'      => 0,

    'quantity' => 1,
    'total'    => $variant->amount,

    'created_at' => now(),
    'updated_at' => now()
]);


             $phone = $payment->customer_phone;



             ChatSession::where('phone', $phone)->update([
    'step' => 'start',
    'data' => null
]);





        $this->messenger->send(
            $phone,
            "✅ *Payment Successful*\n\n".
            "Thank you for your order!\n\n".
            "Amount Paid: ₹".$payment->amount."\n\n".
            "Your order will be processed shortly.\n".
            "🙏 Sri Devi Herbals"
        );




        return view('payment_success');
            // return response()->json([
            //     'status'=>'success',
            //     'message'=>'Payment successful'
            // ]);
        }

        // return response()->json([
        //     'status'=>'pending',
        //     'message'=>'Payment not completed'
        // ]);

        return view('payment_pending');

    } catch (\Exception $e) {

        return response()->json([
            'status'=>'error',
            'message'=>$e->getMessage()
        ]);
    }
}




private function convertWebpToJpg($webpPath)
{
    $fullPath = storage_path('app/public/'.$webpPath);

    if(!file_exists($fullPath)){
        Log::error('Image file not found',[
            'path'=>$fullPath
        ]);
        return $webpPath;
    }

    $jpgPath = str_replace('.webp','.jpg',$webpPath);
    $jpgFullPath = storage_path('app/public/'.$jpgPath);

    if(!file_exists($jpgFullPath)){

        $image = imagecreatefromwebp($fullPath);

        imagejpeg($image,$jpgFullPath,90);

        imagedestroy($image);
    }

    return $jpgPath;
}


}
