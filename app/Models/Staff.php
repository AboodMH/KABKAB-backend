<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
     use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'salary',
        'work_hours',
        'break_hours',
        'off_days',
        'image',
        'user_id',
    ];

    protected $casts = [
        'salary' => 'float',
        'work_hours' => 'integer',
        'break_hours' => 'integer',
        'off_days' => 'integer',
    ];

    // العلاقة مع المستخدم (User)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
