<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCollection extends Model
{
    protected $table = 'employee_credit_collections';

    protected $fillable = [
        'credit_sale_id',
        'sale_id',
        'store_id',
        'person_id',
        'person_type',
        'amount',
        'payment_method',
        'payment_method_label',
        'cash_amount',
        'card_amount',
        'collection_date',
        'collected_by',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'collection_date' => 'date',
        'meta' => 'array',
    ];

    public function creditSale()
    {
        return $this->belongsTo(CreditSale::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function person()
    {
        return $this->morphTo();
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
