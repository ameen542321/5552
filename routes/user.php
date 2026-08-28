<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DailySalesController;
use App\Http\Controllers\EmployeeActionsController;
use App\Http\Controllers\Employees\EmployeeController;
use App\Http\Controllers\InternalUseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductStockController;
use App\Http\Controllers\Store\EmployeeFinanceController;
use App\Http\Controllers\Store\ExpenseController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreTransferController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Users\UserDashboardController;
use App\Http\Controllers\UserNotificationSendController;
use App\Http\Controllers\Users\UserSettingController;
use App\Http\Controllers\Users\SupportTicketController;
use App\Http\Controllers\Cashier\InvoiceController;
use App\Http\Controllers\Tools\StockMovementDateCorrectionController;
use App\Http\Controllers\InventoryCountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public & Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/user/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/employees/check-email', [EmployeeController::class, 'checkEmail'])->name('user.employees.checkEmail');

/*
|--------------------------------------------------------------------------
| Unified Owner Routes (الملاك)
|--------------------------------------------------------------------------
*/
Route::middleware(['owner.unified'])->prefix('user')->name('user.')->group(function () {

    // أداة مؤقتة ومقصورة على المالك/الأدمن لمراجعة تاريخ حركات التوريد قبل تعديله.
    Route::get('/tools/stock-movement-dates', [StockMovementDateCorrectionController::class, 'index'])
        ->name('tools.stock-movement-dates.index');
    Route::post('/tools/stock-movement-dates', [StockMovementDateCorrectionController::class, 'update'])
        ->name('tools.stock-movement-dates.update');

    // --- 1. لوحة التحكم والتقارير العامة ---
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::post('/support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('/support-tickets/{ticket}/messages', [SupportTicketController::class, 'message'])->name('support-tickets.messages.store');
    Route::patch('/support-tickets/{ticket}/close', [SupportTicketController::class, 'close'])->name('support-tickets.close');
    Route::patch('/support-tickets/{ticket}/reopen', [SupportTicketController::class, 'reopen'])->name('support-tickets.reopen');
    // صفحة مستقلة لتنبيهات المالك الإدارية؛ لا تدخل ضمن مسارات المحاسب أو صلاحياته.
    Route::get('/administrative-alerts', [UserDashboardController::class, 'administrativeAlerts'])
        ->name('administrative-alerts');
    Route::get('/dashboard/daily-snapshot', [UserDashboardController::class, 'dailySnapshot'])
        ->name('dashboard.daily-snapshot');
    Route::post('/dashboard/suspended-employees/{employee}/traveler', [UserDashboardController::class, 'dismissSuspendedEmployeeAlert'])
        ->name('dashboard.suspended-employees.traveler');
    Route::delete('/dashboard/suspended-employees/{employee}/terminate', [UserDashboardController::class, 'terminateSuspendedEmployee'])
        ->name('dashboard.suspended-employees.terminate');
    Route::post('/welcome/continue', function () {
        auth('web')->user()->update(['welcome_shown' => true]);
        return redirect()->route('user.dashboard');
    })->name('welcome.continue');
    // ========== ✅ مسارات الاشتراكات (Subscriptions) ==========
    Route::prefix('subscription')->name('subscription.')->group(function () {
        // صفحة انتهاء الاشتراك
        Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');

        // صفحة تجديد الاشتراك (اختيار الخطة)
        Route::get('/renew', [SubscriptionController::class, 'renew'])->name('renew');

        // معالجة طلب التجديد
        Route::post('/renew/process', [SubscriptionController::class, 'processRenew'])->name('processRenew');

        // سجل الاشتراكات السابقة
        Route::get('/history', [SubscriptionController::class, 'history'])->name('history');

        // إلغاء اشتراك (soft delete)
        Route::delete('/{id}/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');

        // (اختياري) عرض فاتورة الاشتراك
        Route::get('/{id}/invoice', [SubscriptionController::class, 'invoice'])->name('invoice');

        // (اختياري) تحميل فاتورة PDF
        Route::get('/{id}/download-pdf', [SubscriptionController::class, 'downloadPdf'])->name('download-pdf');
});
    // --- 2. إدارة المتاجر (Stores) ---
    Route::prefix('stores')->name('stores.')->group(function () {
        Route::get('/', [StoreController::class, 'index'])->name('index');
        Route::get('/create', [StoreController::class, 'create'])->name('create');
        Route::get('/trash', [StoreController::class, 'trash'])->name('trash');
        Route::post('/store', [StoreController::class, 'store'])->name('store');
        Route::get('/sales-chart', [StoreController::class, 'salesChart'])->name('sales.chart');
        // الاستعادة والحذف النهائي يعملان على متجر محذوف لا يستطيع route model binding تمريره إلى store.check.
        Route::post('/{id}/restore', [StoreController::class, 'restore'])->name('restore');
        Route::delete('/{id}/force-delete', [StoreController::class, 'forceDelete'])->name('forceDelete');

        // رابط التبديل (متاح دائماً للمالك)
        Route::patch('/{store}/toggle-status', [StoreController::class, 'toggleStatus'])->name('toggle-status');

        // تطبيق الحارس على العمليات "داخل" المتجر فقط
            Route::middleware(['store.check'])->group(function () {
            Route::prefix('/{store}/inventory-counts')->name('inventory-counts.')->group(function () {
                Route::get('/', [InventoryCountController::class, 'index'])->name('index');
                Route::get('/create', [InventoryCountController::class, 'create'])->name('create');
                Route::post('/selection', [InventoryCountController::class, 'updateSelection'])->name('selection');
                Route::post('/', [InventoryCountController::class, 'store'])->name('store');
                Route::get('/{inventoryCount}', [InventoryCountController::class, 'show'])->name('show');
                Route::post('/{inventoryCount}/send', [InventoryCountController::class, 'send'])->name('send');
                Route::post('/{inventoryCount}/items/{item}/decision', [InventoryCountController::class, 'decide'])->name('items.decision');
                Route::post('/{inventoryCount}/bulk-approve', [InventoryCountController::class, 'bulkApprove'])->name('bulk-approve');
                Route::get('/{inventoryCount}/pdf', [InventoryCountController::class, 'pdf'])->name('pdf');
                Route::delete('/{inventoryCount}', [InventoryCountController::class, 'destroy'])->name('destroy');
            });
            Route::get('/{store}', [StoreController::class, 'show'])->name('show');
            Route::get('/{store}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::get('/{store}/details', [StoreController::class, 'details'])->name('details');
            Route::put('/{store}', [StoreController::class, 'update'])->name('update');
            Route::delete('/{store}', [StoreController::class, 'destroy'])->name('destroy');
            Route::post('/{store}/restore-second-shift', [StoreController::class, 'restoreSecondShift'])->name('restore-second-shift');
            Route::get('/{store}/shift-gaps', [StoreController::class, 'shiftGaps'])->name('shift-gaps');
            Route::post('/{store}/shift-gaps/request-accountant', [StoreController::class, 'requestAccountantShiftInput'])->name('shift-gaps.request-accountant');
            // توضيح: يستخدمه المالك من نافذة/صفحة مراجعة الشفتات لإلغاء الطلب الحالي قبل إعادة إرساله لمحاسب آخر.
            Route::patch('/{store}/shift-gaps/request-accountant/cancel', [StoreController::class, 'cancelAccountantShiftInputRequest'])->name('shift-gaps.request-accountant.cancel');
            Route::patch('/{store}/shift-gaps/request-accountant/reassign', [StoreController::class, 'reassignAccountantShiftInputRequest'])->name('shift-gaps.request-accountant.reassign');
            Route::post('/{store}/shift-gaps/zero-close', [StoreController::class, 'zeroCloseShiftGap'])->name('shift-gaps.zero-close');
            Route::get('/{store}/reports', [StoreController::class, 'reportsIndex'])->name('reports.index');
            Route::get('/{store}/reports/search', [StoreController::class, 'reportsComprehensiveSearch'])->name('reports.search');
            Route::get('/{store}/reports/last-10-days', [StoreController::class, 'reportsLastTenDays'])->name('reports.last-ten-days');
            Route::get('/{store}/reports/monthly', [StoreController::class, 'reportsMonthly'])->name('reports.monthly');
            Route::get('/{store}/reports/monthly/pdf', [StoreController::class, 'reportsMonthlyPdf'])->name('reports.monthly.pdf');
            Route::get('/{store}/reports/employees/monthly', [StoreController::class, 'reportsEmployeesMonthly'])->name('reports.employees.monthly');
            Route::get('/{store}/reports/employees/monthly/pdf', [StoreController::class, 'reportsEmployeesMonthlyPdf'])->name('reports.employees.monthly.pdf');

            // ========== ✅ مبيعات اليوم ==========
            Route::get('/{store}/daily-sales', [DailySalesController::class, 'index'])->name('daily');
            Route::put('/{store}/daily-sales/{sale}', [DailySalesController::class, 'update'])->name('daily.update');
            Route::delete('/{store}/daily-sales/{sale}', [DailySalesController::class, 'destroy'])->name('daily.destroy');
            Route::put('/{store}/daily-sales/financial/{type}/{id}', [DailySalesController::class, 'updateFinancialOperation'])->name('daily.financial.update');
            Route::delete('/{store}/daily-sales/financial/{type}/{id}', [DailySalesController::class, 'destroyFinancialOperation'])->name('daily.financial.destroy');

            // ========== ✅ فواتير المتجر (للمالك) ==========
            Route::prefix('/{store}/invoices')->name('invoices.')->group(function () {
                Route::get('/', [InvoiceController::class, 'ownerIndex'])->name('index');
                Route::get('/create', [InvoiceController::class, 'ownerCreate'])->name('create');
                Route::post('/store', [InvoiceController::class, 'ownerStore'])->name('store');
                Route::get('/{invoice}', [InvoiceController::class, 'ownerShow'])->name('show');
                Route::get('/{invoice}/edit', [InvoiceController::class, 'ownerEdit'])->name('edit');
                Route::get('/{invoice}/print', [InvoiceController::class, 'ownerPrint'])->name('print');
                Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPDF'])->name('pdf');
                Route::put('/{invoice}', [InvoiceController::class, 'ownerUpdate'])->name('update');
                Route::delete('/{invoice}', [InvoiceController::class, 'ownerDestroy'])->name('destroy');
            });

            // ========== ✅ نظام الاستهلاك الداخلي (معدل) ==========
            Route::prefix('{store}/internal-use')->name('internal-use.')->group(function () {
                // عرض صفحة التقرير (HTML)
                Route::get('/report-view', [InternalUseController::class, 'reportView'])->name('report.view');

                // API لجلب بيانات التقرير (AJAX)
                Route::get('/report', [InternalUseController::class, 'report'])->name('report');

                // API لجلب تفاصيل منتج معين
                Route::get('/product-details', [InternalUseController::class, 'productDetails'])->name('product-details');

                // تصدير PDF (قيد التطوير)
                Route::get('/export-pdf', [InternalUseController::class, 'exportPDF'])->name('export-pdf');

                // إضافة استهلاك من المخزون للمالك (بدون دمج مع المصاريف)
                Route::get('/add-consumption', [InternalUseController::class, 'reportView'])->name('add-consumption');
                Route::post('/add-consumption', [InternalUseController::class, 'storeByOwner'])->name('add-consumption.store');
                Route::get('/trash', [InternalUseController::class, 'trashOwnerPurchases'])->name('trash');
                Route::patch('/trash/{purchase}/restore', [InternalUseController::class, 'restoreOwnerPurchase'])->name('trash.restore');
                Route::delete('/trash/{purchase}/force-delete', [InternalUseController::class, 'forceDeleteOwnerPurchase'])->name('trash.force-delete');
                Route::put('/add-consumption/{purchase}', [InternalUseController::class, 'updateOwnerPurchase'])->name('add-consumption.update');
                Route::delete('/add-consumption/{purchase}', [InternalUseController::class, 'destroyOwnerPurchase'])->name('add-consumption.destroy');
                Route::put('/accountant-consumption/{sale}', [InternalUseController::class, 'updateAccountantConsumption'])->name('accountant-consumption.update');
                Route::delete('/accountant-consumption/{sale}', [InternalUseController::class, 'destroyAccountantConsumption'])->name('accountant-consumption.destroy');
                Route::get('/add-consumption/export-pdf', [InternalUseController::class, 'exportPDF'])->name('add-consumption.export-pdf');

                // البحث عن المنتجات (للمحاسب)
                Route::get('/products/search', [InternalUseController::class, 'searchProducts'])->name('products.search');
            });

            Route::prefix('{store}')->group(function () {
                // التصنيفات
                Route::prefix('categories')->name('categories.')->group(function () {
                    Route::get('/', [CategoryController::class, 'index'])->name('index');
                    Route::get('/create', [CategoryController::class, 'create'])->name('create');
                    Route::post('/', [CategoryController::class, 'store'])->name('store');
                    Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
                    Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
                    Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
                    Route::put('/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('toggle-status');
                    Route::get('/trash', [CategoryController::class, 'trash'])->name('trash');
                    Route::put('/{category}/restore', [CategoryController::class, 'restore'])->name('restore');
                    Route::delete('/{category}/force-delete', [CategoryController::class, 'forcedelete'])->name('force-delete');
                });

                // المنتجات

                require __DIR__.'/purchase-orders.php';

                Route::prefix('transfers')->name('transfers.')->group(function () {
                    Route::get('/', [StoreTransferController::class, 'index'])->name('index');
                    Route::get('/create', [StoreTransferController::class, 'create'])->name('create');
                    Route::post('/', [StoreTransferController::class, 'store'])->name('store');
                    Route::post('/{transfer}/approve', [StoreTransferController::class, 'approve'])->name('approve');
                    Route::post('/{transfer}/owner-approve', [StoreTransferController::class, 'ownerApprove'])->name('owner-approve');
                    Route::post('/{transfer}/reject', [StoreTransferController::class, 'reject'])->name('reject');
                    Route::post('/{transfer}/cancel', [StoreTransferController::class, 'cancel'])->name('cancel');
                });

                Route::prefix('products')->name('products.')->group(function () {
                    Route::get('/', [ProductController::class, 'index'])->name('index');
                    Route::get('/create', [ProductController::class, 'create'])->name('create');
                    Route::get('/audit', [ProductController::class, 'auditIndex'])->name('audit');
                    Route::post('/', [ProductController::class, 'store'])->name('store');
                    Route::get('/export/csv', [ProductController::class, 'exportCsv'])->name('export.csv');
                    Route::post('/import/csv', [ProductController::class, 'importCsv'])->name('import.csv');
                    Route::get('/{product}/price-history', [ProductController::class, 'priceHistory'])->name('price-history');
                    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
                    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
                    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
                    Route::put('/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('toggle-status');
                    Route::get('/trash', [ProductController::class, 'trash'])->name('trash');
                    Route::delete('/trash/empty', [ProductController::class, 'emptyTrash'])->name('trash.empty');
                    Route::put('/{id}/restore', [ProductController::class, 'restore'])->name('restore');
                    Route::delete('/{id}/force-delete', [ProductController::class, 'forcedelete'])->name('force-delete');
                    Route::patch('/{id}/archive-message', [ProductController::class, 'updateArchiveMessage'])->name('archive-message');

                    // إدارة المخزون الفردي
                    Route::get('/{product}/stock', [ProductStockController::class, 'index'])->name('stock');
                    Route::post('/{product}/stock/audit-confirm', [ProductStockController::class, 'confirmAudit'])->name('stock.audit-confirm');
                    Route::delete('/{product}/stock/audit-confirm', [ProductStockController::class, 'cancelAuditConfirmation'])->name('stock.audit-confirm.cancel');
                    Route::post('/{product}/stock/increase', [ProductStockController::class, 'increase'])->name('stock.increase');
                    Route::post('/{product}/stock/decrease', [ProductStockController::class, 'decrease'])->name('stock.decrease');
                });

                // صفحة المصروفات الخاصة بالمالك داخل المتجر: متابعة وإضافة وتعديل وحذف.
                Route::prefix('expenses')->name('expenses.')->group(function () {
                    Route::get('/', [ExpenseController::class, 'index'])->name('index');
                    Route::post('/', [ExpenseController::class, 'store'])->name('store');
                    Route::get('/export-pdf', [ExpenseController::class, 'exportPdf'])->name('export-pdf');
                    Route::put('/{id}', [ExpenseController::class, 'update'])->name('update');
                    Route::delete('/{id}', [ExpenseController::class, 'destroy'])->name('destroy');
                });

                Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            });
        });
    }); // نهاية Stores

    // --- 4. إدارة الموظفين ---
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/trash', [EmployeeController::class, 'trash'])->name('trash');
        Route::post('/{id}/restore', [EmployeeController::class, 'restore'])->name('restore');
        Route::delete('/{id}/force-delete', [EmployeeController::class, 'forceDelete'])->name('forceDelete');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
        Route::patch('/{employee}/suspend', [EmployeeController::class, 'suspend'])->name('suspend');
        Route::patch('/{employee}/activate', [EmployeeController::class, 'activate'])->name('activate');
        Route::post('/{employee}/promote', [EmployeeController::class, 'promote'])->name('promote');
        Route::post('/{employee}/demote', [EmployeeController::class, 'demote'])->name('demote');
        Route::get('/{employee}/actions', [EmployeeActionsController::class, 'index'])->name('actions');
        Route::get('/{employee}/export-log', [EmployeeController::class, 'exportSnappy'])->name('exportLog');
        Route::post('/{employee}/absence', [EmployeeActionsController::class, 'storeAbsence'])->name('absence.store');
        Route::post('/{employee}/debt', [EmployeeActionsController::class, 'storeDebt'])->name('debt.store');
        Route::patch('/debt/{debt}', [EmployeeActionsController::class, 'updateDebt'])->name('debt.update');
        Route::post('/{person}/withdrawal', [EmployeeActionsController::class, 'storeWithdrawal'])->name('withdrawal.store');
        Route::post('/debt/collect/full/{debt}', [EmployeeActionsController::class, 'collectFull'])->name('debt.collect.full');
        Route::post('/debt/collect/partial/{debt}', [EmployeeActionsController::class, 'collectPartial'])->name('debt.collect.partial');
    });

    // --- 5. القسم المالي ---
    Route::prefix('employees')->group(function () {
        Route::get('/expense', [ExpenseController::class, 'index'])->name('expense.page');
        Route::post('/expense/store', [ExpenseController::class, 'store'])->name('expense.store');
        Route::get('/expense/export-pdf', [ExpenseController::class, 'exportPdf'])->name('expense.export-pdf');
        Route::delete('/expense/{id}', [ExpenseController::class, 'destroy'])->name('expense.destroy');
        Route::get('/expense/edit/{id}', [ExpenseController::class, 'edit'])->name('expense.edit');
        Route::put('/expense/update/{id}', [ExpenseController::class, 'update'])->name('expense.update');
    });

    // --- 6. الإشعارات ---
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/send', [UserNotificationSendController::class, 'create'])->name('send');
        Route::post('/send', [UserNotificationSendController::class, 'store'])->name('send.store');
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/{id}', [NotificationController::class, 'show'])->name('show');
        Route::post('/{id}/toggle', [NotificationController::class, 'toggle'])->name('toggle');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all', [NotificationController::class, 'markAll'])->name('markAll');
        Route::post('/mark-selected', [NotificationController::class, 'markSelected'])->name('markSelected');
        Route::delete('/{id}', [NotificationController::class, 'delete'])->name('delete');
    });

    // --- 7. الإعدادات ---
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [UserSettingController::class, 'index'])->name('index');
        Route::post('/update', [UserSettingController::class, 'update'])->name('update');
        Route::post('/settings/update', [UserSettingController::class, 'update'])->name('main.update');
    });

}); // نهاية مجموعة المالك
