<?php

namespace Tests\Feature;

use App\Services\Accounting\ProfitRecognitionService;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CreditReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_report_view_contains_collection_and_debt_sections_with_new_breakdown(): void
    {
        $html = view('pdf.pdf_report', [
            'data' => [
                'store_name' => 'متجر الاختبار',
                'accountant_name' => 'محاسب الاختبار',
                'business_date' => '2026-07-18',
                'total_sales' => 0,
                'products_details' => [],
                'sales_breakdown' => [],
                'credit_collections' => ['total' => 30, 'from_current_period' => 30, 'from_old_period' => 0],
                'outgoing_today' => ['total' => 0],
                'cash_details' => ['expected' => 0, 'actual' => 0, 'difference' => 0],
                'details_tables' => [
                    'all_sales' => [],
                    'expenses_list' => [],
                    'withdrawals_list' => [],
                    'collections' => [[
                        'type' => 'partial_collection',
                        'collection_kind' => 'credit',
                        'credit_note' => 'اسم عملية الآجل',
                        'employee_name' => 'أحمد',
                        'operation_date' => '2026-07-18',
                        'collection_date' => '2026-07-18 10:00:00',
                        'cash_amount' => 10,
                        'card_amount' => 20,
                        'amount' => 30,
                        'remaining_after_collection' => 70,
                    ]],
                    'accountant_debts' => [[
                        'id' => 1,
                        'time' => '10:10 AM',
                        'employee_name' => 'أحمد',
                        'description' => 'مديونية اختبار',
                        'operation_date' => '2026-07-18',
                        'amount' => 50,
                    ]],
                ],
                'accountant_finance_movements' => ['included_debt_rows' => []],
            ],
        ])->render();

        $this->assertStringContainsString('تحصيلات الأجل والمديونية', $html);
        $this->assertStringContainsString('تحصيل أجل جزئي', $html);
        $this->assertStringContainsString('اسم عملية الآجل', $html);
        $this->assertStringContainsString('مديونيات سجلها المحاسب', $html);
        $this->assertStringContainsString('مديونية اختبار', $html);
    }

    public function test_profit_recognition_report_numbers_split_recognized_and_deferred_profit(): void
    {
        $stats = app(ProfitRecognitionService::class)->fromSales(new Collection([
            (object) [
                // إجمالي 70 = تكلفة 40 + ربح 30؛ حُصّل 40 وبقي 30 ربحًا مؤجلًا.
                'products_total' => 70,
                'labor_total' => 0,
                'profit' => 30,
                'final_total' => 70,
                'paid_amount' => 40,
                'cash_amount' => 40,
                'card_amount' => 0,
                'remaining_amount' => 30,
            ],
        ]));

        $this->assertSame(40.0, (float) $stats['recognized_cost']);
        $this->assertSame(0.0, (float) $stats['uncovered_cost']);
        $this->assertSame(0.0, (float) $stats['recognized_profit']);
        $this->assertSame(30.0, (float) $stats['deferred_profit']);
    }
}
