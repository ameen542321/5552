<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyBalance extends Model
{
    protected $fillable = [
        'store_id', 'accountant_id', 'system_sales_total',
        'system_cash_expected', 'actual_cash_submitted',
        'difference', 'start_time', 'end_time', 'business_date', 'closed_at',
        'next_shift_business_date', 'next_shift_decision', 'next_shift_decided_by', 'notes'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
        'business_date' => 'date',
        'closed_at' => 'datetime',
        'next_shift_business_date' => 'date',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function accountant() {
        return $this->belongsTo(Accountant::class);
    }
}
