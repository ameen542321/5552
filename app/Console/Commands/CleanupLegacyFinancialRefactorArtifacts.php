<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CleanupLegacyFinancialRefactorArtifacts extends Command
{
    protected $signature = 'refactor:cleanup-legacy-financial
        {--apply : Delete known obsolete refactor artifacts. Without it, only report what would happen}
        {--confirm= : Required confirmation token when --apply is used}';

    protected $description = 'Dry-run or remove known obsolete financial refactor artifacts after manual validation.';

    private const CONFIRMATION = 'DELETE_LEGACY_FINANCIAL_REFACTOR_ARTIFACTS';

    /**
     * Files that are safe to remove after the refactor because their logic moved to services or a unified document.
     * The command intentionally does not delete database rows or remove accounting fallbacks from active services.
     */
    private array $obsoleteFiles = [
        'app/Services/NotificationQueryService.php',
        'app/Support/QuickSaleDeductionCalculator.php',
        'tests/Unit/QuickSaleDeductionCalculatorTest.php',
        'app/Http/Controllers/Users/اضافات مهمة لصفحة وكنترول المالك.md',
        'docs/FINANCIAL_REFACTORING_ROADMAP_AR.md',
        'docs/EMPLOYEE_ACCOUNTANT_OPERATIONS_REVIEW_AR.md',
        'docs/SINGLE_RESPONSIBILITY_SERVICE_EXTRACTION_AR.md',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && $this->option('confirm') !== self::CONFIRMATION) {
            $this->error('Refusing to delete. Re-run with --confirm=' . self::CONFIRMATION);
            return self::FAILURE;
        }

        $rows = collect($this->obsoleteFiles)->map(function (string $path) use ($apply) {
            $absolutePath = base_path($path);
            $exists = File::exists($absolutePath);
            $status = $exists ? 'would_delete' : 'already_missing';

            if ($apply && $exists) {
                File::delete($absolutePath);
                $status = 'deleted';
            }

            return [
                'path' => $path,
                'exists_before_run' => $exists,
                'status' => $status,
            ];
        })->values();

        $report = [
            'mode' => $apply ? 'apply' : 'dry_run',
            'generated_at' => now()->toIso8601String(),
            'confirmation_required_for_apply' => self::CONFIRMATION,
            'database_rows_deleted' => false,
            'active_accounting_fallbacks_removed' => false,
            'files' => $rows->all(),
            'notes' => [
                'This command only removes known obsolete files/artifacts.',
                'It does not delete financial data from the database.',
                'It does not remove fallback code from active accounting services; do that only after manual numeric comparison.',
            ],
        ];

        Storage::disk('local')->put(
            'refactor/legacy_financial_cleanup_report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->table(['Path', 'Exists Before Run', 'Status'], $rows->map(fn (array $row) => [
            $row['path'],
            $row['exists_before_run'] ? 'yes' : 'no',
            $row['status'],
        ])->all());

        $this->info('Report written to storage/app/refactor/legacy_financial_cleanup_report.json');
        $this->warn('No database rows were deleted. Active accounting fallbacks were not removed.');

        return self::SUCCESS;
    }
}
