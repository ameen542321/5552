<?php

namespace Tests\Unit;

use App\Models\InventoryCountSession;
use PHPUnit\Framework\TestCase;

class InventoryCountInterfaceContractTest extends TestCase
{
    public function test_accountant_administrative_tasks_group_contains_the_three_required_destinations(): void
    {
        $navbar = file_get_contents(__DIR__.'/../../resources/views/dashboard/navbars/accountant.blade.php');

        $this->assertStringContainsString('مهام إدارية', $navbar);
        $this->assertStringContainsString("route('accountant.transfers.index')", $navbar);
        $this->assertStringContainsString("route('accountant.purchase-orders.index')", $navbar);
        $this->assertStringContainsString("route('accountant.inventory-counts.index')", $navbar);
        $this->assertStringContainsString('openAdministrativeTasks', $navbar);
    }

    public function test_inventory_count_product_selector_uses_audit_cards_and_lamp_help(): void
    {
        $selector = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/create.blade.php');

        $this->assertStringContainsString('<x-ui.help', $selector);
        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3', $selector);
        $this->assertStringContainsString('ui-dot-danger', $selector);
        $this->assertStringContainsString('الكمية:', $selector);
        $this->assertStringContainsString('البيع:', $selector);
        $this->assertStringContainsString('التكلفة:', $selector);
    }

    public function test_inventory_count_operational_guidance_is_exposed_through_help_components(): void
    {
        foreach ([
            'owner/index.blade.php',
            'owner/create.blade.php',
            'accountant/index.blade.php',
            'accountant/show.blade.php',
        ] as $view) {
            $contents = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/'.$view);
            $this->assertStringContainsString('<x-ui.help', $contents, $view);
        }
    }

    public function test_inventory_reference_contains_its_creation_date_and_sequence(): void
    {
        $session = new InventoryCountSession;
        $session->id = 27;
        $session->created_at = '2026-08-28 12:30:00';

        $this->assertSame('INV-20260828-000027', $session->referenceCode());
    }

    public function test_description_is_used_for_search_but_not_rendered_in_inventory_pages_or_pdf(): void
    {
        $ownerController = file_get_contents(__DIR__.'/../../app/Http/Controllers/InventoryCountController.php');
        $views = implode("\n", array_map(
            static fn (string $path): string => file_get_contents($path),
            glob(__DIR__.'/../../resources/views/inventory-counts/**/*.blade.php') ?: []
        ));
        $pdf = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/pdf.blade.php');

        $this->assertStringContainsString("orWhere('description', 'like'", $ownerController);
        $this->assertStringNotContainsString('product_description_snapshot', $views);
        $this->assertStringNotContainsString('<th>الوصف</th>', $pdf);
    }

    public function test_store_page_links_to_inventory_sessions_and_inventory_status(): void
    {
        $storePage = file_get_contents(__DIR__.'/../../resources/views/user/stores/show.blade.php');

        $this->assertStringContainsString("route('user.stores.inventory-counts.index'", $storePage);
        $this->assertStringContainsString('إدارة جلسات الجرد', $storePage);
        $this->assertStringContainsString('حالة جرد المنتجات', $storePage);
    }

    public function test_accountant_fields_explain_draft_save_and_only_offer_relevant_units(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/accountant/show.blade.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/Accountant/InventoryCountController.php');

        $this->assertStringContainsString('placeholder="مثال: 12"', $view);
        $this->assertStringContainsString('placeholder="مثال: الكمية موزعة على رفّين"', $view);
        $this->assertStringContainsString('حفظ كمية المنتج', $view);
        $this->assertStringContainsString('يحفظ هذا الزر كمية هذا المنتج مؤقتًا', $view);
        $this->assertStringContainsString("return ['roll', 'meter']", $controller);
        $this->assertStringContainsString("return ['kit', 'piece']", $controller);
        $this->assertStringContainsString("Rule::in(\$this->allowedUnits", $controller);
    }

    public function test_owner_comparison_explains_snapshot_time_and_supports_bulk_approval(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/show.blade.php');
        $service = file_get_contents(__DIR__.'/../../app/Services/InventoryCountService.php');

        $this->assertStringContainsString('كمية النظام وقت حفظ المحاسب', $view);
        $this->assertStringContainsString('وقت المقارنة:', $view);
        $this->assertStringContainsString('inventory-bulk-approval', $view);
        $this->assertStringContainsString('اعتماد المنتجات المحددة', $view);
        $this->assertStringContainsString('لا يتغير رصيد المخزون تلقائيًا', $view);
        $this->assertStringContainsString('system_snapshot_at', $service);
        $this->assertStringContainsString("['returned', 'recounted']", $service);
    }

    public function test_temporary_single_product_mode_is_visible_and_legacy_audit_is_the_fallback(): void
    {
        $index = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/index.blade.php');
        $create = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/create.blade.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InventoryCountController.php');
        $show = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/show.blade.php');

        $this->assertStringContainsString('تنبيه اختبار مؤقت', $index);
        $this->assertStringContainsString('إعادة الحد الأدنى إلى خمسة منتجات', $index);
        $this->assertStringContainsString('count($selected) >= 1', $create);
        $this->assertStringContainsString("whereNull('inventory_count_session_item_id')", $controller);
        $this->assertStringContainsString('آخر جرد سابق:', $show);
    }

    public function test_accountant_submit_explains_and_confirms_what_will_happen(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/accountant/show.blade.php');

        $this->assertStringContainsString('عند الإرسال ستنتقل الكميات المحفوظة', $view);
        $this->assertStringContainsString('data-ui-confirm=', $view);
        $this->assertStringContainsString('إرسال نتائج الجرد للمالك', $view);
        $this->assertStringContainsString('جارٍ إرسال النتائج...', $view);
        $this->assertStringContainsString('خطوة إلزامية:', $view);
        $this->assertStringContainsString('احفظ كميات المنتجات أولًا', $view);
        $this->assertStringContainsString('لم يتم إرسال النتائج:', $view);
    }

    public function test_draft_review_pdf_fonts_and_dashboard_inventory_alert_follow_shared_interfaces(): void
    {
        $draft = file_get_contents(__DIR__.'/../../resources/views/inventory-counts/owner/show.blade.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/InventoryCountController.php');
        $dashboardController = file_get_contents(__DIR__.'/../../app/Http/Controllers/Accountant/DashboardController.php');
        $dashboard = file_get_contents(__DIR__.'/../../resources/views/dashboard/accountant/index.blade.php');

        $this->assertStringContainsString('مراجعة المسودة قبل الإرسال', $draft);
        $this->assertStringContainsString('تعديل المنتجات أو المحاسب', $draft);
        $this->assertStringContainsString('إرسال جلسة الجرد للمحاسب', $draft);
        $this->assertStringContainsString('ArabicPdf as PDF', $controller);
        $this->assertStringContainsString("PDF::loadView('inventory-counts.pdf'", $controller);
        $this->assertStringContainsString('pendingInventoryCountSessions', $dashboardController);
        $this->assertStringContainsString('طلبات الجرد', $dashboard);
    }
}
