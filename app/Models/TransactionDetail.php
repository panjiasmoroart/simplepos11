<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    protected $guarded = [];

    /**
     * Relasi ke model Product.
     * Setiap TransactionDetail hanya terkait dengan satu product.
    */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
