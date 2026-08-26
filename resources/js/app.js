import './bootstrap';
import './features/font-readiness';
import './features/dashboard-navigation';
import './features/dashboard/owner-dashboard';
import './features/dashboard-notifications';
import './features/ui-dialogs';
import './features/ui-help-modal';
import './features/ui-actions';
import './features/general-view-actions';
import './features/notifications/interface-actions';
import './features/purchase-orders/index-filter';
import './features/purchase-orders/form-interface';
import './features/purchase-orders/show-interface';
import './features/invoices/create-form';
import './features/invoices/edit-form';
import './features/accountant/expense-interface';
import './features/employees/actions-interface';
import './features/employees/index-interface';
import './features/employees/debt-interface';
import './features/subscriptions/renew-interface';
import './features/shifts/gap-confirmations';
import './features/cashier/internal-use-interface';
import './features/cashier/owner-purchases-interface';
import './features/cashier/tint-sale-interface';
import './features/cashier/quick-sale-interface';
// ربط عقود data-* للواجهات المالية الحساسة بدل خصائص الأحداث المضمّنة داخل Blade.
import './features/sensitive-interface-actions';
// تأكيد الموازنة وإشعار واتساب منفصلان لحماية نموذج الشفت من الربط المضمّن.
import './features/accountant/balance-confirmation';
// سلوك تحصيل الآجل مستخرج بالكامل من Blade مع إبقاء بيانات الصفحة في عقد إعداد فقط.
import './features/accountant/credit-collection';
// سلوك المديونيات مستخرج من Blade، وتبقى المسارات في عقد إعداد الصفحة فقط.
import './features/accountant/debt-operations';
// سلوك المبيعات اليومية مستخرج من Blade مع عقد إعداد يحفظ بيانات الصفحة الحالية.
import './features/stores/daily-sales';
import './features/stores/store-sales-chart';
import './features/admin-notification-recipients';
import './features/security-session-monitor';
import './features/store-products/product-form-shared';
import './features/store-products/product-create-interface';
import './features/store-products/product-edit-interface';
import './features/store-products/product-catalog';
import './features/store-products/inventory-system';
import './features/store-categories/category-system';
import './features/user-logs/log-details';
import './features/store-transfers/transfer-system';
import './features/auth/welcome-progress';
import './features/ui-flash-messages';
import '../css/app.css';
import '@fortawesome/fontawesome-free/css/all.min.css';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

window.Alpine = Alpine;
Alpine.start();

document.addEventListener("DOMContentLoaded", () => {

    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const openBtn = document.getElementById("openSidebarBtn");
    const closeBtn = document.getElementById("closeSidebarBtn");

    if (!sidebar || !overlay || !openBtn || !closeBtn) return;

    let longPressTimer = null;
    const longPressDuration = 500;
    let miniMode = false;
    let firstOpen = true;
    let isOpen = false;

    function openSidebar() {
        sidebar.classList.remove("translate-x-full");
        overlay.classList.remove("hidden");

        openBtn.classList.add("hidden");
        closeBtn.classList.remove("hidden");

        isOpen = true;
    }

    function closeSidebar() {
        sidebar.classList.add("translate-x-full");
        overlay.classList.add("hidden");

        closeBtn.classList.add("hidden");
        openBtn.classList.remove("hidden");

        isOpen = false;
    }

    function toggleMiniMode() {
        miniMode = !miniMode;
        sidebar.classList.toggle("w-20", miniMode);
        sidebar.classList.toggle("w-64", !miniMode);
    }

    openBtn.addEventListener("mousedown", () => {
        longPressTimer = setTimeout(() => {
            if (!firstOpen) toggleMiniMode();
        }, longPressDuration);
    });

    openBtn.addEventListener("mouseup", () => {
        clearTimeout(longPressTimer);

        if (!isOpen) {
            openSidebar();
            firstOpen = false;
        }
    });

    closeBtn.addEventListener("click", closeSidebar);
    overlay.addEventListener("click", closeSidebar);
});
