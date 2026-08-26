<?php

namespace Tests\Unit;

use App\Http\Controllers\DailySalesController;
use App\Models\Debt;
use App\Models\Store;
use ReflectionMethod;
use Tests\TestCase;

class DailySalesDebtDeletionPolicyTest extends TestCase
{
    public function test_debt_operations_are_not_manually_deletable_even_after_settlement(): void
    {
        $method = new ReflectionMethod(DailySalesController::class, 'canDeleteDebtOperation');
        $method->setAccessible(true);
        $controller = app(DailySalesController::class);
        $store = new Store(['id' => 1]);

        $activeDebt = new Debt(['amount' => 100, 'status' => Debt::STATUS_PENDING]);
        $settledDebt = new Debt(['amount' => 0, 'status' => Debt::STATUS_DEDUCTED]);
        $collectionRow = new Debt(['amount' => -25, 'status' => Debt::STATUS_PENDING]);

        $this->assertFalse($method->invoke($controller, $activeDebt, $store));
        $this->assertFalse($method->invoke($controller, $settledDebt, $store));
        $this->assertFalse($method->invoke($controller, $collectionRow, $store));
    }
}
