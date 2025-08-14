<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $table = 'daily_reports';

    protected $fillable = [
        'date',
        'shift',
        'cash',
        'card',
        'refund',
        'expense',
        'withdrawal',
        'amount_in_box',
        'difference',
        'note',
        'user_id',
    ];

    // علاقة التقرير مع المستخدم (User)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
