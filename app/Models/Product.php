<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'product_no',
        'product_name',
        'buy_price',
        'sell_price',
        'quantity',
        'image',
        'state',
    ];

    // علاقة المنتج بالمدخلات (Input)
    public function inputs()
    {
        return $this->hasMany(Input::class);
    }

    // علاقة المنتج بالمخرجات (Output)
    public function outputs()
    {
        return $this->hasMany(Output::class);
    }
}
