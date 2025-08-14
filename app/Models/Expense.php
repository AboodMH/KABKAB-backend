<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'amount',
        'payment_method',
        'note',
        'invoice_image',
        'user_id',
        'is_deported',
        'report_no',
    ];

    // علاقة المصروف بالمستخدم
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
