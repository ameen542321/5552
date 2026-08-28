<?php

namespace Tests\Unit;

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
}
