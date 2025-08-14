<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    // الحقول التي يمكن ملؤها بشكل جماعي (mass assignable)
    protected $fillable = [
        'name',
        'address',
        'discount',
        'balance',
    ];

    // إذا أردت تربط علاقات مثل الفواتير أو المدفوعات يمكنك إضافتها هنا
    // مثال علاقة فواتير الإدخال
    public function inputInvoices()
    {
        return $this->hasMany(InputInvoice::class);
    }

    // مثال علاقة مدفوعات الشركة
    public function payments()
    {
        return $this->hasMany(CompanyPayment::class);
    }
}
