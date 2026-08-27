<?php

use App\Modules\PurchaseOrders\Controllers\StorePurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/', [StorePurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create', [StorePurchaseOrderController::class, 'create'])->name('create');
    Route::post('/', [StorePurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{order}', [StorePurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{order}/edit', [StorePurchaseOrderController::class, 'edit'])->name('edit');
    Route::put('/{order}', [StorePurchaseOrderController::class, 'update'])->name('update');
    Route::get('/{order}/pdf', [StorePurchaseOrderController::class, 'pdf'])->name('pdf');
    Route::post('/{order}/inventory-count/return', [StorePurchaseOrderController::class, 'returnForInventoryCount'])->name('inventory-count.return');
    Route::post('/{order}/inventory-count/approve', [StorePurchaseOrderController::class, 'approveInventoryReview'])->name('inventory-count.approve');
    Route::post('/{order}/inventory-count/reject-items', [StorePurchaseOrderController::class, 'rejectInventoryItems'])->name('inventory-count.reject-items');
    Route::post('/{order}/items/{item}/restore', [StorePurchaseOrderController::class, 'restoreExcludedItem'])->name('items.restore');
    Route::post('/{order}/mark-sent', [StorePurchaseOrderController::class, 'markSent'])->name('mark-sent');
    Route::post('/{order}/items/{item}/owner-product', [StorePurchaseOrderController::class, 'storeOwnerPurchaseProduct'])->name('items.owner-product.store');
    Route::post('/{order}/receive', [StorePurchaseOrderController::class, 'receive'])->name('receive');
    Route::post('/{order}/approve', [StorePurchaseOrderController::class, 'approve'])->name('approve');
    Route::post('/{order}/cancel', [StorePurchaseOrderController::class, 'cancel'])->name('cancel');
    Route::post('/{order}/reject', [StorePurchaseOrderController::class, 'reject'])->name('reject');
    Route::post('/{order}/reopen', [StorePurchaseOrderController::class, 'reopen'])->name('reopen');
    Route::patch('/{order}/support-status', [StorePurchaseOrderController::class, 'supportUpdateStatus'])->name('support-status');
    Route::patch('/{order}/support-restore', [StorePurchaseOrderController::class, 'supportRestore'])->name('support-restore')->withTrashed();
    Route::post('/{order}/support-reverse', [StorePurchaseOrderController::class, 'supportReverse'])->name('support-reverse');
    Route::delete('/{order}/support-purge', [StorePurchaseOrderController::class, 'supportPurge'])->name('support-purge')->withTrashed();
    Route::delete('/{order}', [StorePurchaseOrderController::class, 'destroy'])->name('destroy');
});
