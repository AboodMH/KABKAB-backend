<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'method',
        'amount',
        'amount_paid',
        'change',
        'input_invoice_id',
        'output_invoice_id',
        'note',
        'user_id',
        'is_deported',
        'report_no',
    ];

    // علاقة الدفع مع فاتورة الإدخال (إن وجدت)
    public function inputInvoice()
    {
        return $this->belongsTo(InputInvoice::class);
    }

    // علاقة الدفع مع فاتورة الإخراج (إن وجدت)
    public function outputInvoice()
    {
        return $this->belongsTo(OutputInvoice::class);
    }

    // علاقة الدفع مع المستخدم
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
