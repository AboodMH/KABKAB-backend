<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Output extends Model
{
    use HasFactory;

    protected $fillable = [
        'output_invoice_id',
        'product_id',
        'price',
        'quantity',
        'is_deported',
    ];

    // علاقة الإخراج بالفاتورة (output invoice)
    public function outputInvoice()
    {
        return $this->belongsTo(OutputInvoice::class, 'id', 'output_invoice_id');
    }

    // علاقة الإخراج بالمنتج
    public function product()
    {
        return $this->belongsTo(Product::class, 'id', 'product_id');
    }
}
