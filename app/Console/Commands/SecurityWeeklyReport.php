<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Console\Command;

class SecurityWeeklyReport extends Command
{
    protected $signature = 'security:weekly-report';
    protected $description = 'ينشئ تقرير الموقف الأمني الأسبوعي ويرسله للمدير';

    public function handle(): int
    {
        $since = now()->subWeek();
        $detected = SecurityEvent::where('detected_at', '>=', $since)->count();
        $contained = SecurityEvent::where('contained_at', '>=', $since)->count();
        $verified = SecurityEvent::where('verified_at', '>=', $since)->count();
        $open = SecurityEvent::open()->count();
        $adminIds = User::where('role', User::ROLE_ADMIN)->pluck('id')->all();

        if ($adminIds !== []) {
            Notification::create([
                'sender_id' => null,
                'sender_type' => 'system',
                'target_type' => 'users',
                'target_ids' => $adminIds,
                'title' => 'تقرير الموقف الأمني الأسبوعي',
                'message' => "سيدي، رصدنا {$detected} بلاغًا، واحتوينا {$contained}، وأغلقنا بعد التحقق {$verified}. المتبقي المفتوح: {$open}.",
                'channel' => 'site',
                'read_by' => [],
                'data' => ['report' => 'security_weekly', 'generated_at' => now()->toIso8601String()],
            ]);
        }

        $this->info("detected={$detected} contained={$contained} verified={$verified} open={$open}");
        return self::SUCCESS;
    }
}
