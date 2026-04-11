<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameDetail extends Model
{
    protected $guarded = [];

    /**
     * Relasi ke model Product.
     * Setiap StockOpnameDetail hanya terkait dengan satu product.
    */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi ke model StockTotal.
     * Setiap StockOpnameDetail hanya terkait dengan satu stock total.
     * Parameter tambahan ('product_id', 'product_id') menunjukkan bahwa relasi menggunakan kolom product_id sebagai kunci untuk menghubungkan kedua tabel.
    */
    public function stockTotal()
    {
        return $this->belongsTo(StockTotal::class, 'product_id', 'product_id');
    }
}
