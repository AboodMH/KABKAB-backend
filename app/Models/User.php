<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Session;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role',
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    // علاقة موظف (Staff)
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    // فواتير إدخال (Input Invoices)
    public function inputInvoices()
    {
        return $this->hasMany(InputInvoice::class);
    }

    // فواتير إخراج (Output Invoices)
    public function outputInvoices()
    {
        return $this->hasMany(OutputInvoice::class);
    }

    // علاقة مع temporary_inputs
    public function temporaryInputs()
    {
        return $this->hasMany(TemporaryInput::class, 'user_id');
    }

    // علاقة مع temporary_outputs
    public function temporaryOutputs()
    {
        return $this->hasMany(TemporaryOutput::class, 'user_id');
    }

    // المدفوعات (Payments)
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // المصاريف (Expenses)
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // السحوبات (Withdrawals)
    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    // التقارير اليومية (Daily Reports)
    public function dailyReports()
    {
        return $this->hasMany(DailyReport::class);
    }

    // عمليات التبادل (Exchanges)
    public function exchanges()
    {
        return $this->hasMany(Exchange::class);
    }

    // الجلسات (Sessions)
    public function sessions()
    {
        return $this->hasMany(Session::class);
    }
}
