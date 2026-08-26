<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use Illuminate\Console\Command;

class CleanupSecurityEvents extends Command
{
    protected $signature = 'security:cleanup {--dry-run : عرض العدد دون حذف}';
    protected $description = 'يطبق سياسة الاحتفاظ على الحوادث الأمنية المغلقة';

    public function handle(): int
    {
        $days = max(30, (int) config('security_command_center.retention_days', 180));
        $query = SecurityEvent::query()
            ->whereIn('status', ['resolved', 'false_positive'])
            ->where('resolved_at', '<', now()->subDays($days));
        $count = (clone $query)->count();

        if (! $this->option('dry-run') && $count > 0) {
            $query->delete();
        }

        $this->info(($this->option('dry-run') ? 'سيحذف' : 'تم حذف')." {$count} بلاغًا بعد مدة احتفاظ قدرها {$days} يومًا.");
        return self::SUCCESS;
    }
}
