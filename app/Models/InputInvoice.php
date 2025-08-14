<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InputInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'company_id',
        'date',
        'value',
        'quantity',
        'note',
        'type',
        'user_id',
    ];

    // علاقة الفاتورة بالشركة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // علاقة الفاتورة بالمستخدم (الموظف/الكاشير)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // علاقة الفاتورة بالمدخلات (المنتجات المرتبطة بالفاتورة)
    public function inputs()
    {
        return $this->hasMany(Input::class, 'invoice_no', 'invoice_no');
    }

    // علاقة الفاتورة بالمدفوعات (إذا تستخدم جدول المدفوعات)
    public function payments()
    {
        return $this->hasMany(Payment::class, 'input_invoice_id');
    }
}
