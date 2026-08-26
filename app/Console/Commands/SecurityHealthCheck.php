<?php

namespace App\Console\Commands;

use App\Services\SecurityEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityHealthCheck extends Command
{
    protected $signature = 'security:health-check';
    protected $description = 'يرصد مشكلات التشغيل الأساسية ويسجلها في مركز القيادة الأمنية';

    public function handle(SecurityEventService $security): int
    {
        Cache::put('security:health:last_run', now()->toIso8601String(), now()->addMinutes(15));

        if (config('app.debug')) {
            $security->record('OPS.DEBUG_ENABLED', 'operations', 'high', 'سيدي، وضع التصحيح مفعل في التطبيق.', ['confidence' => 100]);
        }

        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $security->record('OPS.FAILED_JOBS', 'operations', $failedJobs >= 10 ? 'high' : 'medium', 'سيدي، توجد مهام خلفية فاشلة تحتاج المراجعة.', [
                    'confidence' => 100,
                    'evidence' => ['failed_jobs_count' => $failedJobs],
                ]);
            }
        }

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if (is_float($free) && is_float($total) && $total > 0 && ($free / $total) < 0.1) {
            $security->record('OPS.DISK_LOW', 'operations', 'high', 'سيدي، مساحة التخزين المتاحة منخفضة.', [
                'confidence' => 100,
                'evidence' => ['free_percent' => round(($free / $total) * 100, 2)],
            ]);
        }

        $this->info('تم تنفيذ الفحص الأمني والتشغيلي.');
        return self::SUCCESS;
    }
}
