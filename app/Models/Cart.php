<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = [];

     /**
     * Relasi dengan model Product
     * Setiap item/product di cart/keranjang akan terkait dengan product yg sesuai
    */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
