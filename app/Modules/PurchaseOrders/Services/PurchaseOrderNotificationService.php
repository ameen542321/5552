<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class PurchaseOrderNotificationService
{
    public function afterCommit(array $attributes): void
    {
        DB::afterCommit(static fn () => Notification::create($attributes));
    }
}
