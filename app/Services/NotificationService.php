<?php

namespace App\Services;

use App\Events\NewNotificationCreated;
use App\Models\User;
use App\Models\Store;
use App\Models\Accountant;
use App\Models\Notification;

class NotificationService
{
    /**
     * إرسال إشعار عام (بعد تحديد المستهدفين)
     */
    public static function send(array $data)
    {
        // حلّ المستهدفين
        $resolvedTargets = self::resolveTargets(
            $data['target_type'],
            $data['target_ids'] ?? null
        );

        // إنشاء الإشعار
        $notification = Notification::create([
            'sender_id'    => $data['sender_id'] ?? null,
            'sender_type'  => $data['sender_type'],
            'target_type'  => $data['target_type'],
            'target_ids'   => $resolvedTargets,
            'title'        => $data['title'],
            'message'      => $data['message'],
            'template_key' => $data['template_key'] ?? null,
            'channel'      => $data['channel'] ?? 'site',
            'read_by'      => [],
        ]);

        // بث الإشعار فورًا عبر WebSockets
        self::broadcastNotification($notification);

        return $notification;
    }

    /**
     * إرسال إشعار من قالب ثابت مع دعم المتغيرات
     */
    public static function sendTemplate(string $templateKey, array $data)
    {
        $template = config("notification_templates.$templateKey");

        if (!$template) {
            throw new \Exception("Template '$templateKey' not found.");
        }

        // استبدال المتغيرات داخل العنوان والرسالة
       foreach ($template as $key => $value) {
    foreach ($data as $dataKey => $dataValue) {

        if (is_string($value)) {

            // 🔥 تحويل أي قيمة إلى نص آمن
            if (is_array($dataValue) || is_object($dataValue)) {
                $dataValue = json_encode($dataValue, JSON_UNESCAPED_UNICODE);
            } elseif (!is_string($dataValue)) {
                $dataValue = (string) $dataValue;
            }

            $template[$key] = str_replace(":$dataKey", $dataValue, $template[$key]);
        }
    }
}


        return self::send([
            'sender_id'    => $data['sender_id'] ?? null,
            'sender_type'  => $data['sender_type'],
            'target_type'  => $data['target_type'],
            'target_ids'   => $data['target_ids'] ?? null,
            'title'        => $template['title'],
            'message'      => $template['message'],
            'template_key' => $templateKey,
            'channel'      => $data['channel'] ?? 'site',
        ]);
    }

    /**
     * بث الإشعار عبر WebSockets
     */
    private static function broadcastNotification(Notification $notification)
    {
        // إرسال للجميع (Users + Accountants)
        if ($notification->target_type === 'all') {

            foreach (User::pluck('id') as $userId) {
                event(new NewNotificationCreated($notification, $userId));
            }

            foreach (Accountant::pluck('id') as $accId) {
                event(new NewNotificationCreated($notification, $accId));
            }

            return;
        }

        // إرسال لمستخدمين محددين
        if ($notification->target_type === 'users') {
            foreach ($notification->target_ids ?? [] as $userId) {
                event(new NewNotificationCreated($notification, $userId));
            }
            return;
        }

        // إرسال لمحاسبين محددين
        if ($notification->target_type === 'accountants') {
            foreach ($notification->target_ids ?? [] as $accId) {
                event(new NewNotificationCreated($notification, $accId));
            }
            return;
        }

        // إرسال لمتاجر (المالك فقط)
        if (in_array($notification->target_type, ['store', 'stores'])) {
            $storeOwners = Store::whereIn('id', $notification->target_ids ?? [])
                ->pluck('user_id')
                ->toArray();

            foreach ($storeOwners as $ownerId) {
                event(new NewNotificationCreated($notification, $ownerId));
            }
            return;
        }
    }

    /**
     * تحديد المستهدفين حسب نوع الإرسال
     */
    private static function resolveTargets(string $type, $ids)
    {
        if ($type === 'all') {
            return null;
        }

        if ($type === 'user' || $type === 'users') {
            return self::sendToUsers($ids);
        }

        if ($type === 'store' || $type === 'stores') {
            return self::sendToStores($ids);
        }

        if ($type === 'accountants') {
            return self::sendToAccountants($ids);
        }

        return $ids;
    }

    /**
     * حلّ مستهدفين من نوع Users
     */
    private static function sendToUsers($ids)
    {
        if (!$ids) return [];

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return User::whereIn('id', $ids)->pluck('id')->toArray();
    }

    /**
     * حلّ مستهدفين من نوع Stores
     */
    private static function sendToStores($ids)
    {
        if (!$ids) return [];

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return Store::whereIn('id', $ids)->pluck('id')->toArray();
    }

    /**
     * حلّ مستهدفين من نوع Accountants
     */
    private static function sendToAccountants($ids)
    {
        if (!$ids) return [];

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        return Accountant::whereIn('id', $ids)->pluck('id')->toArray();
    }
}
