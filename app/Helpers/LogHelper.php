<?php

namespace App\Helpers;

use App\Models\Log;
use Illuminate\Support\Facades\Auth;

class LogHelper
{

 public static function writeLog(array $data)
    {
        try {
            Log::create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'actor_type' => 'admin',
                'actor_id' => auth()->id(),
                'model_type' => $data['model_type'] ?? null,
                'model_id' => $data['model_id'] ?? null,
                'action' => $data['action'],
                'description' => $data['details'],
                'details' => json_encode($data['details'] ?? []),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('فشل كتابة السجل: ' . $e->getMessage());
        }
    }
public static function add($action, $description, $storeId = null)
{
    // الفاعل (User أو Accountant)
    $actor = auth()->user() ?? auth('accountant')->user();

    if (!$actor) {
        return;
    }

    // تحديد المتجر
    $storeId = $storeId ?? ($actor->current_store_id ?? null);

    if (!$storeId) {
        return;
    }

    // جلب المتجر
    $store = \App\Models\Store::find($storeId);

    if (!$store) {
        return;
    }

    // جلب صاحب المتجر الحقيقي
    $ownerId = $store->user_id;

    if (!$ownerId) {
        return;
    }

    // تسجيل اللوق باسم صاحب المتجر
    \App\Models\Log::create([
        'user_id'     => $ownerId,   // ✔ صاحب المتجر
        'store_id'    => $storeId,
        'action'      => $action,
        'description' => $description,
    ]);
}



}
