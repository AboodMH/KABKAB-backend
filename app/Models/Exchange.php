<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use HasFactory;

    protected $table = 'exchanges';

    protected $fillable = [
        'input_invoice_no',
        'output_invoice_no',
        'previous_output_invoice_no',
        'note',
        'user_id',
    ];

    // روابط المفاتيح الأجنبية مع جداول الفواتير والمستخدم

    public function inputInvoice()
    {
        return $this->belongsTo(InputInvoice::class, 'id', 'input_invoice_no');
    }

    public function outputInvoice()
    {
        return $this->belongsTo(OutputInvoice::class, 'id', 'output_invoice_no');
    }

    public function previousOutputInvoice()
    {
        return $this->belongsTo(OutputInvoice::class, 'id', 'previous_output_invoice_no');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'user_id');
    }
}
