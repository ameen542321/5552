<?php

namespace App\Console\Commands;

use App\Services\Employees\EmployeeTransferRepairService;
use Illuminate\Console\Command;
use RuntimeException;

class RepairEmployeeTransfers extends Command
{
    private const CONFIRMATION = 'REPAIR_EMPLOYEE_TRANSFERS';

    protected $signature = 'employees:repair-transfers
        {--apply : تنفيذ التصحيح؛ الوضع الافتراضي معاينة فقط}
        {--backup-confirmed : تأكيد أخذ نسخة احتياطية واختبار استرجاعها}
        {--confirm= : عبارة التأكيد المطلوبة عند التنفيذ}
        {--batch=100 : عدد السجلات في كل معاملة}';

    protected $description = 'معاينة أو تصحيح نسب سجلات الموظفين المنقولين إلى المتاجر مع سجل قبل/بعد';

    public function handle(EmployeeTransferRepairService $repair): int
    {
        $preview = $repair->preview();
        $affected = collect($preview['affectedByStore']);

        $this->table(['من متجر', 'إلى متجر', 'عدد السجلات', 'مجموع القيم'], $affected->map(fn ($row) => [
            $row['from_store_id'],
            $row['to_store_id'],
            $row['rows_count'],
            number_format($row['amount_total'], 2),
        ])->all());

        $this->line('حسابات محاسبين غير متطابقة: ' . collect($preview['mismatchedAccountants'])->count());
        $this->line('مبيعات يتيمة: ' . collect($preview['orphanSales'])->count());
        $this->line('سجلات نقل ناقصة: ' . collect($preview['incompleteTransfers'])->count());
        $this->line('لقطات رصيد مكررة: ' . collect($preview['duplicateBalanceSnapshots'])->count());

        if (! $this->option('apply')) {
            $this->warn('معاينة فقط؛ لم تتغير أي بيانات. بعد مراجعة التقرير والنسخة الاحتياطية استخدم --apply مع خيارات التأكيد.');

            return self::SUCCESS;
        }

        if (! $this->option('backup-confirmed') || $this->option('confirm') !== self::CONFIRMATION) {
            $this->error('أُلغي التنفيذ. يلزم --backup-confirmed و --confirm=' . self::CONFIRMATION);

            return self::FAILURE;
        }

        try {
            $result = $repair->apply($preview, max(1, (int) $this->option('batch')));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("تم تصحيح {$result['repairedRows']} سجلًا تاريخيًا و{$result['repairedAccountants']} حساب محاسب.");
        $this->info('لم تُعدّل أي قيمة مالية أو عملية بيع أو معرف محاسب؛ عُدّل نسب المتجر فقط وسُجل أثر قبل/بعد.');

        return self::SUCCESS;
    }
}
