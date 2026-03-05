<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTaxAffinity extends Model
{
    //

    public $table      = 'product_tax_affinity';
    protected $guarded = [

    ];

    protected $casts = [
        'gst_enabled'      => 'boolean',
        'affinity_enabled' => 'boolean',
        'gst_percent'      => 'float',
        'affinity_percent' => 'float',
    ];

}
