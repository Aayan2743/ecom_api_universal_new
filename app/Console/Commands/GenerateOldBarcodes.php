<?php

namespace App\Console\Commands;

use App\Models\ProductBarcode;
use App\Models\ProductVariantCombination;
use Illuminate\Console\Command;

class GenerateOldBarcodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-old-barcodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
          $variants = ProductVariantCombination::all();

        foreach ($variants as $variant) {

            $existing = ProductBarcode::where('variant_id',$variant->id)->count();

            $remaining = $variant->quantity - $existing;

            for ($i=0; $i < $remaining; $i++) {

                $barcode = $this->generateEAN13();

                ProductBarcode::create([
                    'variant_id' => $variant->id,
                    'barcode'    => $barcode,
                    'is_used'    => 0
                ]);
            }

        }

        $this->info("Old barcodes generated successfully.");
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
}
