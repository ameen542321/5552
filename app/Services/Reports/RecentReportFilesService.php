<?php

namespace App\Services\Reports;

use App\Models\Store;
use Carbon\Carbon;

class RecentReportFilesService
{
    /**
     * يرجع ملفات PDF المولدة للمتجر خلال آخر عدد محدد من الأيام مع عرض التاريخ المستخرج من اسم التقرير عند توفره.
     */
    public function recentForStore(Store $store, int $days = 10): array
    {
        $reportsFolder = public_path('reports/');
        $cutoffDate = now()->subDays($days)->startOfDay();
        $reportFiles = collect();

        if (is_dir($reportsFolder)) {
            $reportPathPattern = $reportsFolder . 'Report_*_' . $store->id . '.pdf';
            $reportPaths = glob($reportPathPattern) ?: [];

            $reportFiles = collect($reportPaths)
                ->map(function ($reportPath) {
                    $createdAt = Carbon::createFromTimestamp(filemtime($reportPath));
                    $businessDate = $this->businessDateFromReportName(basename($reportPath), $createdAt);

                    return [
                        'name' => basename($reportPath),
                        'url' => url('reports/' . basename($reportPath)),
                        'created_at' => $createdAt,
                        'business_date' => $businessDate,
                        'size_kb' => round(filesize($reportPath) / 1024, 2),
                    ];
                })
                ->filter(fn ($reportFile) => $reportFile['created_at']->greaterThanOrEqualTo($cutoffDate))
                ->sortByDesc('created_at')
                ->values();
        }

        return [
            'reports' => $reportFiles,
            'cutoffDate' => $cutoffDate,
        ];
    }

    private function businessDateFromReportName(string $fileName, Carbon $createdAt): Carbon
    {
        if (preg_match('/([0-9٠-٩]{1,2})[-_\\/]+([0-9٠-٩]{1,2})(?:[-_\\/]+([0-9٠-٩]{2,4}))?/u', $fileName, $matches)) {
            $day = (int) strtr($matches[1], ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            $month = (int) strtr($matches[2], ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
            $year = isset($matches[3]) && $matches[3] !== ''
                ? (int) strtr($matches[3], ['٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'])
                : (int) $createdAt->year;

            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return Carbon::create($year, $month, $day)->startOfDay();
            }
        }

        return $createdAt->copy()->startOfDay();
    }
}
