<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Input extends Model
{
    use HasFactory;

    protected $fillable = [
        'input_invoice_id',
        'product_id',
        'quantity',
    ];

    // العلاقة مع فاتورة الإدخال (InputInvoice)
    public function inputInvoice()
    {
        return $this->belongsTo(InputInvoice::class, 'id', 'input_invoice_id');
    }

    // العلاقة مع المنتج (Product)
    public function product()
    {
        return $this->belongsTo(Product::class, 'id', 'product_id');
    }
}
