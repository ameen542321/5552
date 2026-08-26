<?php

namespace App\Traits;

use App\Models\Store;

trait BelongsToStore
{
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeForStore($query, $storeId)
    {
        if (!$storeId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('store_id', $storeId);
    }

    public function scopeForCurrentStore($query)
    {
        $storeId = null;

        if (auth()->check()) {
            $storeId = auth()->user()->current_store_id;
        } elseif (auth('accountant')->check()) {
            $storeId = auth('accountant')->user()->store_id;
        }

        if (!$storeId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('store_id', $storeId);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }
}
