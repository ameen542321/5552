<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeeTransferAuditService;
use Illuminate\Console\Command;

class AuditEmployeeTransfers extends Command
{
    protected $signature = 'employees:audit-transfers {--json : طباعة التقرير بصيغة JSON}';

    protected $description = 'معاينة سلامة نقل الموظفين والمحاسبين دون تعديل البيانات';

    public function handle(EmployeeTransferAuditService $audit): int
    {
        $report = $audit->report();
        $counts = collect($report)->map->count();

        if ($this->option('json')) {
            $this->line(json_encode(
                collect($report)->map->values()->all(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->table(['الفحص', 'العدد'], [
                ['اختلاف متجر الموظف والمحاسب', $counts['mismatchedAccountants']],
                ['مبيعات فقدت المحاسب', $counts['orphanSales']],
                ['سجلات نقل ناقصة', $counts['incompleteTransfers']],
                ['أرصدة نقل محتسبة بأكثر من سجل', $counts['duplicateBalanceSnapshots']],
                ['حركات تاريخية مشتبه بنقل متجرها', $counts['suspectedMovedHistoricalRows']],
            ]);

            $this->warn('هذا فحص قراءة فقط. لا ينفذ أي تصحيح تلقائي. افحص النتائج وخذ نسخة احتياطية قبل أي معالجة.');
        }

        return $counts->sum() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
