<?php

namespace App\Services;

use App\Models\OneSignalSetting;
use Illuminate\Support\Facades\Http;

class OneSignalService
{
    /**
     * جلب إعدادات OneSignal من قاعدة البيانات
     */
    private static function getConfig()
    {
        return OneSignalSetting::first();
    }

    /**
     * إرسال إشعار إلى أجهزة محددة
     */
    public static function sendToDevices(array $deviceTokens, string $title, string $message, array $data = [])
    {
        if (empty($deviceTokens)) {
            return false;
        }

        $config = self::getConfig();
        if (!$config || !$config->app_id || !$config->api_key) {
            return false;
        }

        $payload = [
            'app_id' => $config->app_id,
            'include_player_ids' => $deviceTokens,
            'headings' => [
                'en' => $title,
                'ar' => $title,
            ],
            'contents' => [
                'en' => $message,
                'ar' => $message,
            ],
            'data' => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$config->api_key}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        return $response->successful() ? $response->json() : false;
    }

    /**
     * إرسال إشعار إلى جميع الأجهزة
     */
    public static function sendToAll(string $title, string $message, array $data = [])
    {
        $config = self::getConfig();
        if (!$config || !$config->app_id || !$config->api_key) {
            return false;
        }

        $payload = [
            'app_id' => $config->app_id,
            'included_segments' => ['All'],
            'headings' => [
                'en' => $title,
                'ar' => $title,
            ],
            'contents' => [
                'en' => $message,
                'ar' => $message,
            ],
            'data' => $data,
        ];

        $response = Http::withHeaders([
            'Authorization' => "Basic {$config->api_key}",
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);

        return $response->successful() ? $response->json() : false;
    }
}
