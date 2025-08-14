<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
     use HasFactory;

    protected $fillable = [
        'date',
        'employee_id',
        'amount',
        'payment_method',
        'note',
        'user_id',
        'is_deported',
        'report_no',
    ];

    // علاقة المستخدم الذي قام بالسحب
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
