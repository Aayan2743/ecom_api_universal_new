<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use App\Models\ProductBarcode;
use App\Models\ProductVariantCombination;

class ProductBarcodeController extends Controller
{
     public function generate(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'variant_id' => 'required|exists:product_variant_combinations,id',
        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $variant = ProductVariantCombination::find($request->variant_id);

        $quantity = $variant->quantity;

        $generated = [];

        for ($i = 1; $i <= $quantity; $i++) {

            // $barcode = $variant->sku . str_pad($i,5,'0',STR_PAD_LEFT);

              $barcode = $this->generateEAN8();


            if (!ProductBarcode::where('barcode',$barcode)->exists()) {

                $data = ProductBarcode::create([
                    'variant_id' => $variant->id,
                    'barcode' => $barcode,
                    'is_used' => 0
                ]);

                $generated[] = $data;
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Barcodes generated successfully',
            'data' => $generated
        ]);

    }

    public function generateOldBarcodes_working()
{
    $variants = ProductVariantCombination::all();

    foreach ($variants as $variant) {

        $existing = ProductBarcode::where('variant_id',$variant->id)->count();

        $remaining = $variant->quantity - $existing;

        for ($i=0; $i<$remaining; $i++) {

            $barcode = $this->generateEAN8();

            ProductBarcode::create([
                'variant_id' => $variant->id,
                'barcode' => $barcode,
                'is_used' => 0
            ]);
        }
    }

   return response()->json([
            'status' => true,
            'message' => 'Old barcodes generated successfully',

        ]);
}

public function generateOldBarcodes()
{
    $variants = ProductVariantCombination::all();

    foreach ($variants as $variant) {

        // Count only UNUSED barcodes
        $unusedBarcodes = ProductBarcode::where('variant_id', $variant->id)
            ->where('is_used', 0)
            ->count();

        // Calculate remaining barcodes needed
        $remaining = $variant->quantity - $unusedBarcodes;

        if ($remaining <= 0) {
            continue;
        }

        for ($i = 0; $i < $remaining; $i++) {

            $barcode = $this->generateEAN8();

            ProductBarcode::create([
                'variant_id' => $variant->id,
                'barcode'    => $barcode,
                'is_used'    => 0
            ]);
        }
    }

    return response()->json([
        'status'  => true,
        'message' => 'Old barcodes generated successfully',
    ]);
}


private function generateEAN8()
{
    do {

        // generate 7 digit base number
        $base = str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);

        $digits = str_split($base);

        $odd = 0;
        $even = 0;

        foreach ($digits as $i => $d) {
            if (($i + 1) % 2 == 0) {
                $even += $d;
            } else {
                $odd += $d;
            }
        }

        // EAN8 checksum formula
        $total = ($odd * 3) + $even;
        $checksum = (10 - ($total % 10)) % 10;

        $ean = $base . $checksum;

    } while (ProductBarcode::where('barcode', $ean)->exists());

    return $ean;
}

    private function generateEAN13()
        {
            do {

                $base = '89'.str_pad(mt_rand(0,99999999),10,'0',STR_PAD_LEFT);

                $digits = str_split($base);
                $odd=0;
                $even=0;

                foreach ($digits as $i=>$d) {
                    if(($i+1)%2==0){
                        $even += $d;
                    }else{
                        $odd += $d;
                    }
                }

                $total = $odd + ($even*3);
                $checksum = (10 - ($total % 10)) % 10;

                $ean = $base.$checksum;

            } while(ProductBarcode::where('barcode',$ean)->exists());

            return $ean;
        }


        public function printBarcodesss($variantId)
{
    $variant = ProductVariantCombination::with(['product','values','barcodes'])
        ->findOrFail($variantId);



    $name = strtoupper(strlen($variant->product->name) > 10
    ? substr($variant->product->name,0,10).'...'
    : $variant->product->name);
    $qty  = $variant->values->pluck('value')->join('/');
    $price = $variant->purchase_price;

    $barcode = $variant->barcodes->first()->barcode ?? '12345678';


$shopName = "ALPHAMEGA MART SUPERMARKET";

$productName = $name;     // e.g. Sugar 1 kg
$mrp         = $$variant->purchase_price;    // MRP
$sellPrice   = $price;    // Selling price (change if needed)
$barcode     = $barcode;  // barcode value

$tspl = "
<xpml><page quantity='0' pitch='25.0 mm'></xpml>
SIZE 100 mm, 25 mm
GAP 3 mm, 0 mm
SET RIBBON ON
DIRECTION 0,0
REFERENCE 0,0
OFFSET 0 mm
SET PEEL OFF
SET CUTTER OFF
<xpml></page></xpml>
<xpml><page quantity='1' pitch='25.0 mm'></xpml>
SET TEAR ON
CLS
CODEPAGE 1252

TEXT 781,184,\"2\",180,1,1,\"{$shopName}\"
TEXT 781,158,\"2\",180,1,1,\"{$productName}\"
TEXT 781,128,\"2\",180,1,1,\"Selling Price : {$sellPrice}/-\"
TEXT 781,98,\"2\",180,1,1,\"MRP : {$mrp}/-\"

BARCODE 784,73,\"128M\",39,0,180,3,6,\"!104{$barcode}\"
TEXT 649,26,\"1\",180,1,1,\"{$barcode}\"

TEXT 381,184,\"2\",180,1,1,\"{$shopName}\"
TEXT 383,158,\"2\",180,1,1,\"{$productName}\"
TEXT 383,128,\"2\",180,1,1,\"Selling Price : {$sellPrice}/-\"
TEXT 381,98,\"2\",180,1,1,\"MRP : {$mrp}/-\"

BARCODE 384,73,\"128M\",39,0,180,3,6,\"!104{$barcode}\"
TEXT 249,26,\"1\",180,1,1,\"{$barcode}\"

PRINT 1,1
<xpml></page></xpml>
<xpml><end/></xpml>
";




$tspl = '
SIZE 100 mm, 25 mm
SET RIBBON ON
DIRECTION 0,0
REFERENCE 0,0
OFFSET 0 mm
SET PEEL OFF
SET CUTTER OFF
SET TEAR ON
CLS
CODEPAGE 1252

TEXT 692,163,"2",180,1,1,"'.$name.'"
TEXT 682,38,"2",180,1,1,"A Cost:'.$price.'/-"
TEXT 654,64,"2",180,1,1,"Qty:'.$qty.'"
BARCODE 757,143,"128M",47,0,180,4,8,"!105'.$barcode.'"
TEXT 655,87,"2",180,1,1,"'.$barcode.'"

TEXT 292,163,"2",180,1,1,"'.$name.'"
TEXT 282,38,"2",180,1,1,"A Cost:'.$price.'/-"
TEXT 254,64,"2",180,1,1,"Qty:'.$qty.'"
BARCODE 357,143,"128M",47,0,180,4,8,"!105'.$barcode.'"
TEXT 255,87,"2",180,1,1,"'.$barcode.'"

PRINT 1,1
';

    return response($tspl)->header('Content-Type','text/plain');
}

public function printBarcodes_old($variantId)
{
    $variant = ProductVariantCombination::with(['product','values','barcodes'])
        ->findOrFail($variantId);

    $name = strtoupper(strlen($variant->product->name) > 20
        ? substr($variant->product->name,0,20).'...'
        : $variant->product->name);

    $price   = $variant->purchase_price;
    $selling_price   = $variant->extra_price;
    // $dicount   = $variant->extra_price;

    $barcode = $variant->barcodes->first()->barcode ?? '12345678';

    $shopName = "ALPHAMEGA MART SUPERMARKET";

    dd( $barcode, $name, $price, $selling_price);

$tspl = '
<xpml><page quantity="0" pitch="25.0 mm"></xpml>
SIZE 100 mm, 25 mm
GAP 3 mm, 0 mm
SET RIBBON ON
DIRECTION 0,0
REFERENCE 0,0
OFFSET 0 mm
SET PEEL OFF
SET CUTTER OFF
<xpml></page></xpml>
<xpml><page quantity="1" pitch="25.0 mm"></xpml>
SET TEAR ON
CLS
CODEPAGE 1252


TEXT 781,158,"2",180,1,1,"'.$name.'"
TEXT 781,128,"2",180,1,1,"Selling Price : '.$price.'/-"
TEXT 781,98,"2",180,1,1,"MRP : '.$price.'/-"

BARCODE 784,73,"128M",39,0,180,3,6,"!104'.$barcode.'"
TEXT 649,26,"1",180,1,1,"'.$barcode.'"


TEXT 383,158,"2",180,1,1,"'.$name.'"
TEXT 383,128,"2",180,1,1,"Selling Price : '.$price.'/-"
TEXT 381,98,"2",180,1,1,"MRP : '.$price.'/-"

BARCODE 384,73,"128M",39,0,180,3,6,"!104'.$barcode.'"
TEXT 249,26,"1",180,1,1,"'.$barcode.'"

PRINT 1,1
<xpml></page></xpml>
<xpml><end/></xpml>
';

    return response($tspl)->header('Content-Type','text/plain');
}


public function printBarcodes_123($variantId)
{
    $variant = ProductVariantCombination::with(['product','values','barcodes'])
        ->findOrFail($variantId);

    // $itemName = strtoupper($variant->product->name);

    $name = $variant->product->name;

$itemName = strtoupper(
    mb_strlen($name) > 8
        ? mb_substr($name, 0, 8) . '....'
        : $name
);
    $qty      = $variant->values->pluck('value')->join('/');
    $actual   = $variant->purchase_price;
    $discount = $variant->extra_price ?? $actual;

    $barcode = $variant->barcodes->first()->barcode ?? '12345678';


$tspl = '
SIZE 100 mm, 25 mm
GAP 3 mm, 0 mm
SET RIBBON ON
DIRECTION 0,0
REFERENCE 0,0
OFFSET 0 mm
SET PEEL OFF
SET CUTTER OFF
SET TEAR ON
CLS
CODEPAGE 1252

TEXT 776,88,"2",180,1,1,"Discount Price : '.$discount.'/-"
TEXT 701,144,"2",180,1,1,'.$qty.'"
TEXT 776,144,"2",180,1,1,"QTY : '.$qty.'"
TEXT 776,120,"2",180,1,1,"Actual Price : '.$actual.'/-"
TEXT 776,178,"2",180,1,1,"ITEM :"
BARCODE 756,64,"128M",40,0,180,2,4,"!105'.$barcode.'"
TEXT 682,16,"1",180,1,1,"'.$barcode.'"
TEXT 679,178,"2",180,1,1,"'.$itemName.'"

TEXT 376,88,"2",180,1,1,"Discount Price : '.$discount.'/-"
TEXT 301,144,"2",180,1,1,'.$qty.'"
TEXT 376,144,"2",180,1,1,"QTY : '.$qty.'"
TEXT 376,120,"2",180,1,1,"Actual Price : '.$actual.'/-"
TEXT 376,178,"2",180,1,1,"ITEM :"
BARCODE 356,64,"128M",40,0,180,2,4,"!105'.$barcode.'"
TEXT 282,16,"1",180,1,1,"'.$barcode.'"
TEXT 279,178,"2",180,1,1,"'.$itemName.'"

PRINT 1,1
';

    return response($tspl)->header('Content-Type','text/plain');
}

public function printBarcodes($variantId)
{
    $variant = ProductVariantCombination::with(['product','values','barcodes'])
        ->findOrFail($variantId);

    $name = $variant->product->name;

    $itemName = strtoupper(
        mb_strlen($name) > 8
            ? mb_substr($name, 0, 8) . '....'
            : $name
    );

    $qty      = $variant->values->pluck('value')->join('/');
    $actual   = $variant->extra_price;
    $discount = $variant->purchase_price - $variant->discount ;

    // get all barcodes
    // $barcodes = $variant->barcodes->pluck('barcode')->toArray();

    $barcodes = $variant->barcodes
    ->take($variant->quantity)
    ->pluck('barcode')
    ->toArray();

    // dd($barcodes);

    $tspl = '
SIZE 100 mm, 25 mm
GAP 3 mm, 0 mm
SET RIBBON ON
DIRECTION 0,0
REFERENCE 0,0
OFFSET 0 mm
SET PEEL OFF
SET CUTTER OFF
SET TEAR ON
CLS
CODEPAGE 1252
';

    for ($i = 0; $i < count($barcodes); $i += 2) {

        $barcode1 = $barcodes[$i] ?? '';
        $barcode2 = $barcodes[$i + 1] ?? '';

        $tspl .= '

TEXT 776,88,"2",180,1,1,"Discount Price : '.$discount.'/-"

TEXT 776,144,"2",180,1,1,"QTY : '.$qty.'"
TEXT 776,120,"2",180,1,1,"Actual Price : '.$actual.'/-"
TEXT 776,178,"2",180,1,1,"ITEM :"
BARCODE 756,64,"128M",40,0,180,2,4,"!105'.$barcode1.'"
TEXT 682,16,"1",180,1,1,"'.$barcode1.'"
TEXT 679,178,"2",180,1,1,"'.$itemName.'"

TEXT 376,88,"2",180,1,1,"Discount Price : '.$discount.'/-"

TEXT 376,144,"2",180,1,1,"QTY : '.$qty.'"
TEXT 376,120,"2",180,1,1,"Actual Price : '.$actual.'/-"
TEXT 376,178,"2",180,1,1,"ITEM :"
BARCODE 356,64,"128M",40,0,180,2,4,"!105'.$barcode2.'"
TEXT 282,16,"1",180,1,1,"'.$barcode2.'"
TEXT 279,178,"2",180,1,1,"'.$itemName.'"

PRINT 1,1
';
    }

    return response($tspl)->header('Content-Type','text/plain');
}




public function productByBarcode($barcode)
{
    $barcodeRecord = ProductBarcode::where('barcode',$barcode)->where('is_used', false)->first();

    if(!$barcodeRecord){
        return response()->json([
            'message' => 'Barcode not found'
        ],404);
    }

    $variant = ProductVariantCombination::with(['product','images','values'])
                ->find($barcodeRecord->variant_id);

    $product = $variant->product;

    return response()->json([
        "id" => $product->id,
        "name" => $product->name,
        "image_url" => $product->image_url ?? null,
        "variants" => [
            [
                "id" => $variant->id,
                "name" => $variant->values->pluck('value')->implode(' '),
                "price" => $variant->purchase_price,
                "stock" => $variant->quantity,
                "images" => $variant->images->map(function($img){
                    return asset('storage/'.$img->image_path);
                })
            ]
        ]
    ]);
}

}
