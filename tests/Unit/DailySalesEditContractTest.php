<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DailySalesEditContractTest extends TestCase
{
    public function test_deleting_all_products_is_submitted_as_an_explicit_edit(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/user/stores/daily.blade.php');
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/DailySalesController.php');

        $this->assertStringContainsString('name="items_submitted" value="1"', $view);
        $this->assertStringContainsString("\$request->boolean('items_submitted')", $controller);
    }

    public function test_missing_paid_amount_is_inferred_and_validation_messages_are_arabic(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/DailySalesController.php');

        $this->assertStringContainsString("'paid_amount' => 'nullable|numeric|min:0'", $controller);
        $this->assertStringContainsString("\$request->filled('paid_amount')", $controller);
        $this->assertStringContainsString("'paid_amount.numeric' => 'يجب أن يكون المبلغ المدفوع رقمًا صالحًا.'", $controller);
        $this->assertStringNotContainsString("'paid_amount' => 'required|numeric|min:0'", $controller);
    }
}
