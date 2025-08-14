<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutputInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'date',
        'value',
        'quantity',
        'note',
        'discount',
        'discount_type',
        'type',
        'user_id',
        'report_no',
    ];

    // علاقة الفاتورة بالمستخدم (الكاشير أو الموظف)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // علاقة الفاتورة بالمدخلات (المنتجات المرتبطة بها)
    public function outputs()
    {
        return $this->hasMany(Output::class, 'invoice_no', 'invoice_no');
    }

    // علاقة الفاتورة بالتقارير اليومية (إن وجدت)
    public function dailyReport()
    {
        return $this->belongsTo(DailyReport::class, 'report_no');
    }

    // علاقة الفاتورة بالمدفوعات (إن وجدت)
    public function payments()
    {
        return $this->hasMany(Payment::class, 'output_invoice_id');
    }
}
