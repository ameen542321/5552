<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;
use App\Models\Concerns\HasAccountingDateScopes;

class Expense extends Model
{
    use SoftDeletes, BelongsToStore, HasAccountingDateScopes;

    protected $fillable = [
        'store_id',
        'user_id',
        'type',
        'employee_id',
        'actor_type',
        'description',
        'amount',
        'business_date',
        'daily_balance_id',
    ];

    protected $casts = [
        'business_date' => 'date',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function dailyBalance()
    {
        return $this->belongsTo(DailyBalance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
