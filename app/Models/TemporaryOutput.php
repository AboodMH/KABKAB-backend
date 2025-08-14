<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'price',
        'quantity',
        'user_id',
    ];

    // علاقة المنتج
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // علاقة المستخدم الذي أضاف الإخراج المؤقت
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
