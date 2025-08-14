<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyPayment extends Model
{
    use HasFactory;

    protected $table = 'company_payments';

    // الحقول القابلة للملء الجماعي
    protected $fillable = [
        'date',
        'company_id',
        'amount',
        'payment_method',
        'note',
    ];

    // علاقة الدفع مع الشركة
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
