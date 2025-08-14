<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryInput extends Model
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
        'user_id',
    ];

    // علاقة المستخدم الذي أضاف الإدخال المؤقت
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
