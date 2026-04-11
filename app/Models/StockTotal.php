<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTotal extends Model
{
    protected $guarded = [];

     /**
     * Relasi ke model Product.
     * Setiap StockTotal hanya terkait dengan satu product.
    */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
