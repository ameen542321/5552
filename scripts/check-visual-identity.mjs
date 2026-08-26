// إصلاح مطبق: تغطي عقود الهوية إصلاحات إدارة المستخدمين وقالب اللوحة والثيم والبطاقات المشتركة.
import fs from 'node:fs';
import postcss from 'postcss';

const cssPath = 'resources/css/app.css';
const css = fs.readFileSync(cssPath, 'utf8');
const root = postcss.parse(css, { from: cssPath });
const failures = [];
const forbiddenAccountingAdjective = '\u0627\u0644\u0645\u062d\u0627\u0633\u0628\u064a';
const forbiddenAccountingWording = new RegExp(`${forbiddenAccountingAdjective}(?:\u0629)?(?![\u0621-\u064a])`, 'u');

// إصلاح شامل مطبق: تفحص هذه البوابة جميع أنظمة الويب المتبقية بدل إضافة عقد منفصل لكل صفحة.
const collectAllViewBladeFiles = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = `${directory}/${entry.name}`;
    return entry.isDirectory() ? collectAllViewBladeFiles(entryPath) : (entry.name.endsWith('.blade.php') ? [entryPath] : []);
});
const collectTextFiles = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = `${directory}/${entry.name}`;
    if (entry.isDirectory()) return collectTextFiles(entryPath);
    return /\.(?:php|blade\.php|js|mjs|md)$/.test(entry.name) ? [entryPath] : [];
});
for (const wordingFile of ['app', 'config', 'database', 'docs', 'resources', 'routes', 'scripts', 'tests'].flatMap(collectTextFiles)) {
    if (forbiddenAccountingWording.test(fs.readFileSync(wordingFile, 'utf8'))) {
        failures.push(`${wordingFile}: forbidden long accounting day/date/month adjective must not appear`);
    }
}
const allViewBladeFiles = collectAllViewBladeFiles('resources/views');
const helpComponent = fs.readFileSync('resources/views/components/ui/help.blade.php', 'utf8');
const shiftGapsView = fs.readFileSync('resources/views/user/stores/shift-gaps.blade.php', 'utf8');

if (!helpComponent.includes("fa-lightbulb")
    || !helpComponent.includes("fa-triangle-exclamation")
    || />\s*[؟!]\s*<\/button>/.test(helpComponent)) {
    failures.push('central help component: semantic icons must replace question/exclamation text');
}
if (/rounded-2xl\s+bg-black\s+border\s+ui-border/.test(shiftGapsView)) {
    failures.push('shift gap operation counters: raw black backgrounds must not replace theme surfaces');
}
const printBladeFile = (filePath) => filePath.includes('/pdf/')
    || filePath.includes('/emails/')
    || filePath.endsWith('/print.blade.php')
    || filePath.endsWith('/invoice-print.blade.php')
    || filePath.endsWith('/modules/purchase-orders/pdf.blade.php')
    || filePath.endsWith('/modules/purchase-orders/inventory-count-pdf.blade.php');
// لا توجد قائمة استثناءات تشغيلية: كل أحداث DOM في Blade يجب أن تمر عبر عقد data-* ووحدة JavaScript.
const inlineEventPattern = /\s(?:onclick|ondblclick|onchange|oninput|onsubmit|onkeydown|onkeyup|onkeypress|onfocus|onblur)\s*=/i;
const inlineStylePattern = /<style\b|\sstyle\s*=|@push\s*\(\s*['"]styles|@section\s*\(\s*['"]styles/i;

for (const bladeFilePath of allViewBladeFiles) {
    const bladeSource = fs.readFileSync(bladeFilePath, 'utf8');
    if (/fa-(?:circle-)?question/.test(bladeSource)) {
        failures.push(`${bladeFilePath}: question-mark help icons must use the centralized lightbulb icon`);
    }
    for (const helpButton of bladeSource.matchAll(/<button\b[^>]*data-ui-help-title[^>]*>[\s\S]*?<\/button>/gu)) {
        if (/؟/u.test(helpButton[0])) {
            failures.push(`${bladeFilePath}: question-mark help icons must use the centralized lightbulb icon`);
        }
        if (/fa-(?:circle-)?exclamation(?!-triangle)/.test(helpButton[0])) {
            failures.push(`${bladeFilePath}: warning help triggers must use the triangle-exclamation icon`);
        }
    }
    if (inlineEventPattern.test(bladeSource)) {
        failures.push(`${bladeFilePath}: inline DOM event handlers are forbidden across all web systems`);
    }
    if (!printBladeFile(bladeFilePath) && inlineStylePattern.test(bladeSource)) {
        failures.push(`${bladeFilePath}: inline styles are forbidden outside print/PDF/email templates`);
    }
    for (const modalTag of bladeSource.matchAll(/<div\b[^>]*(?:id="[^"]*[Mm]odal|x-show="[^"]*[Mm]odal)[^>]*>/gis)) {
        if (/class="[^"]*(?:fixed|absolute)\s+inset-0/i.test(modalTag[0])
            && !modalTag[0].includes('ui-modal-backdrop')) {
            failures.push(`${bladeFilePath}: modal roots must use the centralized ui-modal-backdrop shell`);
        }
    }
}

const inventoryStockView = fs.readFileSync('resources/views/user/stores/products/stock/index.blade.php', 'utf8');
if (!inventoryStockView.includes('inventoryDefaultUnit($product)')
    || !inventoryStockView.includes("'بيع ('")
    || !inventoryStockView.includes("getRawOriginal('quantity')")) {
    failures.push('inventory stock view: movement rows and current balance must follow the default sale unit');
}

const ownerProductsIndexView = fs.readFileSync('resources/views/user/stores/products/index.blade.php', 'utf8');
if (!ownerProductsIndexView.includes("$primaryCostLabel")
    || !ownerProductsIndexView.includes("'عرض مشتريات المالك'")
    || ownerProductsIndexView.includes("'secondary_price_label'")
    || ownerProductsIndexView.includes("'components_label'")
    || ownerProductsIndexView.includes('إجمالي تكلفة المخزون الحالي:')
    || ownerProductsIndexView.includes('سعر التكلفة {{ $costUnitLabel }}')) {
    failures.push('owner product cards: full cards must keep unit details while quick results stay concise');
}

const shiftGapView = fs.readFileSync('resources/views/user/stores/shift-gaps.blade.php', 'utf8');
if (!shiftGapView.includes('<h1 class="text-2xl font-black ui-title">مراجعة الشفتات الناقصة</h1>\n                <x-ui.help')
    || shiftGapView.includes('\n{-- علامة تفعيل')) {
    failures.push('shift gaps: scope help must stay beside the page title and Blade comments must remain valid');
}

const productEditView = fs.readFileSync('resources/views/user/stores/products/edit.blade.php', 'utf8');
if (!productEditView.includes('<label class="ui-title font-bold">استخدام المنتج</label>\n                    <x-ui.help title="شرح استخدام المنتج"')
    || !productEditView.includes('<label for="is_splittable" class="ui-title font-medium">تفعيل نظام البيع كطقم / حبة</label>\n            <x-ui.help variant="warning"')
    || !productEditView.includes('role="switch"')
    || !productEditView.includes('>نظام البيع</label>')
    || !productEditView.includes('id="cost_unit_hint" class="mt-2 ui-text-caption ui-status-warning font-bold"')
    || productEditView.includes('ui-status-warning-bg p-3 ui-text-caption ui-status-warning')) {
    failures.push('product edit: help placement, semantic warning icon, switch, and caution text contracts are missing');
}
if (css.includes('rgba(146, 64, 14') || css.includes('--ui-warning-bg: #fef3c7')) {
    failures.push('warning surfaces: orange-toned backgrounds must not return');
}

// تنظيف مطبق: تمنع هذه القائمة عودة ثلاثة أنظمة Views مهجورة وبياناتها التجريبية.
const removedLegacyViewFiles = [
    'resources/views/subscriptions/assign.blade.php',
    'resources/views/subscriptions/manage.blade.php',
    'resources/views/subscriptions/plans.blade.php',
    'resources/views/subscriptions/show.blade.php',
    'resources/views/admin/subscriptions/index.blade.php',
    'resources/views/accountants/partials/op-icon.blade.php',
    'resources/views/components/store-section.blade.php',
];
for (const removedLegacyViewFile of removedLegacyViewFiles) {
    if (fs.existsSync(removedLegacyViewFile)) {
        failures.push(`${removedLegacyViewFile}: removed unreferenced legacy view must not return`);
    }
}

// إصلاح مطبق: تحمي هذه العقود توحيد تأكيدات خمسة أنظمة ومنع عودة confirm المضمّن إلى Blade.
const centralizedConfirmationViews = [
    'resources/views/components/employee/debt-form.blade.php',
    'resources/views/employees/edit.blade.php',
    'resources/views/employees/trash.blade.php',
    'resources/views/cashier/internal-use/report.blade.php',
    'resources/views/cashier/internal-use/trash.blade.php',
    'resources/views/modules/purchase-orders/user/show.blade.php',
    'resources/views/user/stores/show.blade.php',
    'resources/views/user/administrative-alerts.blade.php',
];
for (const confirmationViewPath of centralizedConfirmationViews) {
    const confirmationView = fs.readFileSync(confirmationViewPath, 'utf8');
    if (!confirmationView.includes('data-ui-confirm=')) {
        failures.push(`${confirmationViewPath}: expected centralized data-ui-confirm contract`);
    }
    if (/onsubmit\s*=\s*["']return\s+confirm\s*\(/.test(confirmationView)) {
        failures.push(`${confirmationViewPath}: inline form confirm must not return`);
    }
}

// إصلاح مطبق: تغطي هذه العقود مركز الإشعارات وتنقل تفاصيل الموظف ومصروفات المحاسب.
const notificationIndexView = fs.readFileSync('resources/views/notifications/index.blade.php', 'utf8');
const notificationShowView = fs.readFileSync('resources/views/notifications/show.blade.php', 'utf8');
const employeeOperationDetailsView = fs.readFileSync('resources/views/components/employee/operation-details-modal.blade.php', 'utf8');
const accountantExpenseView = fs.readFileSync('resources/views/accountants/pos/expense.blade.php', 'utf8');
const sharedDialogsFeature = fs.readFileSync('resources/js/features/ui-dialogs.js', 'utf8');

if (!notificationIndexView.includes('data-ui-confirm-title="تأكيد حذف الإشعار"')
    || !notificationShowView.includes('data-ui-confirm-title="تأكيد حذف الإشعار"')
    || /onsubmit\s*=\s*["']return\s+confirm\s*\(/.test(notificationIndexView + notificationShowView)) {
    failures.push('notification center: deletion must use the centralized confirmation contract');
}
if (!employeeOperationDetailsView.includes('data-ui-confirm-navigation=')
    || !sharedDialogsFeature.includes("a[data-ui-confirm-navigation]")) {
    failures.push('employee operation details: closed-shift navigation confirmation contract is missing');
}
if (!accountantExpenseView.includes('data-ui-confirm-title="تأكيد حذف المصروف"')
    || accountantExpenseView.includes('confirmExpenseDelete(')) {
    failures.push('accountant expenses: deletion must use the centralized confirmation contract');
}

// إصلاح مطبق: تحمي هذه العقود إجراءات خمسة أنظمة بعد نقلها إلى ui-actions.
const uiActionsFeature = fs.readFileSync('resources/js/features/ui-actions.js', 'utf8');
const appEntry = fs.readFileSync('resources/js/app.js', 'utf8');
const employeeAbsenceForm = fs.readFileSync('resources/views/components/employee/absence-form.blade.php', 'utf8');
const employeeWithdrawalForm = fs.readFileSync('resources/views/components/employee/withdrawal-form.blade.php', 'utf8');
const employeeDebtForm = fs.readFileSync('resources/views/components/employee/debt-form.blade.php', 'utf8');
const employeeActionsView = fs.readFileSync('resources/views/employees/actions.blade.php', 'utf8');
const invoicesIndexView = fs.readFileSync('resources/views/invoices/index.blade.php', 'utf8');
const invoicePrintView = fs.readFileSync('resources/views/invoices/print.blade.php', 'utf8');
const quickSalePrintView = fs.readFileSync('resources/views/cashier/quick-sale/invoice-print.blade.php', 'utf8');

if (!appEntry.includes("./features/ui-actions")
    || !uiActionsFeature.includes('[data-ui-show], [data-ui-hide], [data-ui-print]')
    || !uiActionsFeature.includes('[data-ui-sync-value]')) {
    failures.push('ui actions: shared show/hide/print/value-sync feature is not fully wired');
}
if ([employeeAbsenceForm, employeeWithdrawalForm].some((view) => !view.includes('data-ui-sync-value='))
    || [employeeAbsenceForm, employeeWithdrawalForm, employeeDebtForm].some((view) => !view.includes('data-ui-hide='))) {
    failures.push('employee form components: modal/date actions must use ui-actions contracts');
}
if (!employeeActionsView.includes('data-ui-reset-details')
    || employeeActionsView.includes('openEmployeeOperationModal(')) {
    failures.push('employee actions: operation modals must use ui-actions contracts');
}
if (!invoicesIndexView.includes('data-ui-confirm-title="تأكيد حذف الفاتورة"')
    || invoicesIndexView.includes('confirmDelete(')
    || invoicesIndexView.includes('id="deleteForm"')) {
    failures.push('invoices index: deletion must use independent centralized-confirmation forms');
}
if (!invoicePrintView.includes('data-ui-print') || !quickSalePrintView.includes('data-ui-print')) {
    failures.push('invoice printing: print actions must use the shared ui-actions contract');
}
if (!accountantExpenseView.includes('data-ui-show="expenseModal"')
    || !accountantExpenseView.includes('data-ui-hide="editExpenseModal"')
    || !accountantExpenseView.includes('data-ui-edit-form="editExpenseForm"')
    || accountantExpenseView.includes('openExpenseModal()')
    || accountantExpenseView.includes('closeExpenseModal()')
    || accountantExpenseView.includes('openEditExpenseModal(')) {
    failures.push('accountant expenses: basic modal actions must use ui-actions contracts');
}

// إصلاح مطبق: تغطي العقود السلوكيات العامة المستخرجة من أربع واجهات عمليات للمحاسب.
const accountantAbsenceView = fs.readFileSync('resources/views/accountants/pos/absence.blade.php', 'utf8');
const accountantWithdrawalView = fs.readFileSync('resources/views/accountants/pos/withdrawals.blade.php', 'utf8');
const accountantCollectionView = fs.readFileSync('resources/views/accountants/pos/collection.blade.php', 'utf8');
const accountantDebtView = fs.readFileSync('resources/views/accountants/pos/debt.blade.php', 'utf8');
const dailySalesView = fs.readFileSync('resources/views/user/stores/daily.blade.php', 'utf8');
const applicationEntry = fs.readFileSync('resources/js/app.js', 'utf8');

if (accountantCollectionView.includes('<script>')
    || !accountantCollectionView.includes('data-credit-collection-config=')
    || !fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/accountant/credit-collection';")) {
    failures.push('accountant collection: behavior must remain extracted behind the safe config contract');
}
if (accountantDebtView.includes('<script>')
    || !accountantDebtView.includes('data-debt-operations-config=')
    || !fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/accountant/debt-operations';")) {
    failures.push('accountant debt: behavior must remain extracted behind the safe config contract');
}
if (dailySalesView.includes('<script>')
    || !dailySalesView.includes('data-daily-sales-config=')
    || !applicationEntry.includes("import './features/stores/daily-sales';")) {
    failures.push('daily sales: behavior must remain extracted behind the safe config contract');
}
const storeDetailsView = fs.readFileSync('resources/views/user/stores/show.blade.php', 'utf8');
if (storeDetailsView.includes('<script>')
    || !storeDetailsView.includes('data-store-sales-chart-config=')
    || !applicationEntry.includes("import './features/stores/store-sales-chart';")) {
    failures.push('store details: sales chart must remain extracted behind the safe config contract');
}
const ownerDashboardViewSource = fs.readFileSync('resources/views/dashboard/user/index.blade.php', 'utf8');
if (ownerDashboardViewSource.includes('<script>')
    || !ownerDashboardViewSource.includes('data-owner-dashboard-config=')
    || !applicationEntry.includes("import './features/dashboard/owner-dashboard';")) {
    failures.push('owner dashboard: display behavior must remain extracted behind the safe config contract');
}
const invoiceCreateView = fs.readFileSync('resources/views/invoices/create.blade.php', 'utf8');
const invoiceEditView = fs.readFileSync('resources/views/invoices/edit.blade.php', 'utf8');
if (invoiceCreateView.includes('<script>')
    || !invoiceCreateView.includes('data-invoice-create-config=')
    || !applicationEntry.includes("import './features/invoices/create-form';")) {
    failures.push('invoice create: interface behavior must remain extracted behind the safe config contract');
}
if (invoiceEditView.includes('<script>')
    || !invoiceEditView.includes('data-invoice-edit-interface')
    || !applicationEntry.includes("import './features/invoices/edit-form';")) {
    failures.push('invoice edit: interface behavior must remain extracted behind its activation contract');
}

// دفعة العرض العام: تمنع العقود عودة السكربتات التنفيذية إلى الواجهات الخمس المستخرجة.
for (const [viewPath, requiredContract] of Object.entries({
    'resources/views/accountants/pos/searchProduct.blade.php': 'data-accountant-product-search',
    'resources/views/cashier/internal-use/report.blade.php': 'data-exclusive-details',
    'resources/views/notifications/index.blade.php': 'data-notification-select-all',
    'resources/views/notifications/send.blade.php': 'notificationComposer(',
    'resources/views/modules/purchase-orders/user/index.blade.php': 'data-purchase-order-filter',
})) {
    const source = fs.readFileSync(viewPath, 'utf8');
    if (source.includes('<script>') || !source.includes(requiredContract)) {
        failures.push(`${viewPath}: general behavior must remain extracted behind its data contract`);
    }
}

// دفعة واجهات المصروف والموظفين والاشتراك وفجوات الشفت: استخراج الربط فقط دون تغيير النماذج.
for (const [viewPath, contract, moduleImport] of [
    ['resources/views/accountants/pos/expense.blade.php', 'data-expense-interface-config=', "import './features/accountant/expense-interface';"],
    ['resources/views/employees/actions.blade.php', 'data-employee-actions-config=', "import './features/employees/actions-interface';"],
    ['resources/views/employees/index.blade.php', 'data-employee-index-interface', "import './features/employees/index-interface';"],
    ['resources/views/subscriptions/renew.blade.php', 'data-subscription-renew-interface', "import './features/subscriptions/renew-interface';"],
    ['resources/views/user/stores/shift-gaps.blade.php', 'data-shift-gap-interface', "import './features/shifts/gap-confirmations';"],
]) {
    const source = fs.readFileSync(viewPath, 'utf8');
    if (source.includes('<script>') || !source.includes(contract) || !applicationEntry.includes(moduleImport)) {
        failures.push(`${viewPath}: interface behavior must remain extracted behind its activation contract`);
    }
}
for (const [viewPath, contract, moduleImport] of [
    ['resources/views/modules/purchase-orders/user/create.blade.php', 'data-purchase-order-form-config=', "import './features/purchase-orders/form-interface';"],
    ['resources/views/modules/purchase-orders/user/show.blade.php', 'data-purchase-order-show-config=', "import './features/purchase-orders/show-interface';"],
]) {
    const source = fs.readFileSync(viewPath, 'utf8');
    if (source.includes('<script>') || !source.includes(contract) || !applicationEntry.includes(moduleImport)) {
        failures.push(`${viewPath}: purchase-order behavior must remain extracted behind its config contract`);
    }
}
const purchaseOrderShowView = fs.readFileSync('resources/views/modules/purchase-orders/user/show.blade.php', 'utf8');
const purchaseOrderShowFeature = fs.readFileSync('resources/js/features/purchase-orders/show-interface.js', 'utf8');
const purchaseOrderFormFeature = fs.readFileSync('resources/js/features/purchase-orders/form-interface.js', 'utf8');
const accountantPurchaseOrderController = fs.readFileSync('app/Modules/PurchaseOrders/Controllers/AccountantPurchaseOrderController.php', 'utf8');
const purchaseOrderService = fs.readFileSync('app/Modules/PurchaseOrders/Services/StorePurchaseOrderService.php', 'utf8');
const purchaseOrderAlerts = fs.readFileSync('resources/views/components/purchase-order-alerts-button.blade.php', 'utf8');
const accountantDashboardAlerts = fs.readFileSync('resources/views/components/accountant-dashboard-alerts.blade.php', 'utf8');
if (!purchaseOrderShowView.includes('id="summaryItemsSearch"')
    || !purchaseOrderShowView.includes('class="js-summary-item ui-purchase-item-card')
    || !purchaseOrderShowView.includes('سعر الوحدة/الطقم/الرول المستلم')
    || !purchaseOrderShowView.includes('number_format($unitPriceReceipt, 2)')
    || !purchaseOrderShowFeature.includes("document.getElementById('summaryItemsSearch')")) {
    failures.push('purchase-order approval: searchable responsive summary cards must remain available');
}
for (const reviewActionContract of [
    'إعادة للمراجعة والتعديل',
    'data-inventory-selection',
    'data-inventory-search',
    'data-inventory-show-all',
    'data-inventory-select-all',
    'data-inventory-clear',
    'title="متى تستخدم التعديل؟"',
    'title="متى تطلب الجرد؟"',
    'title="ماذا يحدث بعد الإعادة؟"',
    'title="رفض الطلبية"',
    'title="إلغاء الطلبية"',
]) {
    if (!purchaseOrderShowView.includes(reviewActionContract)) {
        failures.push(`purchase-order review actions must remain separate and scalable: ${reviewActionContract}`);
    }
}
if (!purchaseOrderShowFeature.includes("document.querySelector('[data-inventory-selection]')")
    || !purchaseOrderShowFeature.includes("option.classList.toggle('hidden', !matched)")
    || !purchaseOrderShowFeature.includes('let inventoryExpanded = false')
    || !purchaseOrderShowFeature.includes("inventoryExpanded ? 'إخفاء المنتجات' : 'إظهار كل المنتجات'")) {
    failures.push('purchase-order inventory selection must keep searchable bulk-selection behavior');
}
if (!accountantPurchaseOrderController.includes("redirect()->route('accountant.purchase-orders.index')")
    || !purchaseOrderService.includes("['changes' => $itemChanges]")
    || purchaseOrderService.includes("'inventory_review_note' => null")) {
    failures.push('purchase-order edit return must preserve the owner note, changed-item audit, and accountant index redirect');
}
if (!purchaseOrderAlerts.includes('ui-topbar-action group gap-2')
    || !accountantDashboardAlerts.includes('ui-topbar-action group gap-2')) {
    failures.push('accountant dashboard alert icons must expose their simple hover tooltips');
}
for (const approvalCardContract of [
    '<details class="js-summary-item ui-disclosure ui-purchase-item-card"',
    '<summary class="cursor-pointer list-none p-4 ui-hover-surface transition"',
    'المخزون قبل التوريد',
    'المخزون بعد الاعتماد',
    'الكمية المضافة للمخزون',
    'فرق الكمية بين المطلوب والمستلم',
    'تكلفة المنتج الأساسية (لم تتغير)',
    'ProductQuantityFormatter::stockSnapshot',
]) {
    if (!purchaseOrderShowView.includes(approvalCardContract)) {
        failures.push(`purchase-order approved card must preserve: ${approvalCardContract}`);
    }
}
if (!purchaseOrderFormFeature.includes('custom_items[${idx}][items_per_unit]')
    || !purchaseOrderFormFeature.includes('custom_items[${idx}][roll_length]')
    || !purchaseOrderFormFeature.includes('syncCustomUnitDetails')) {
    failures.push('purchase-order form: custom kit and roll details must be captured for automatic owner-product creation');
}
if (purchaseOrderShowView.includes('محفوظ في المنتجات')) {
    failures.push('purchase-order receipt: existing products must not show a redundant saved indicator');
}
if (!purchaseOrderShowView.includes('js-open-owner-product-modal')
    || !purchaseOrderShowView.includes('id="ownerProductModal" class="ui-modal-backdrop hidden"')
    || purchaseOrderShowView.includes('id="productCreateModal"')
    || purchaseOrderShowView.includes('id="ownerProductCategorySearch"')
    || !purchaseOrderShowView.includes('id="ownerProductSaleCategoryField"')
    || !purchaseOrderShowView.includes('id="ownerProductOwnerCategory"')
    || !purchaseOrderShowView.includes('id="ownerProductWasteField" class="hidden')
    || !purchaseOrderShowView.includes('id="ownerProductSaleFields"')
    || !purchaseOrderShowView.includes('id="ownerProductPiecePriceField"')
    || !purchaseOrderShowView.includes('id="ownerProductItemsPerUnitInput" name="items_per_unit"')
    || purchaseOrderShowView.includes('id="ownerProductItemsPerUnitInput" name="items_per_unit" type="number" min="2" class="ui-input hidden"')
    || !purchaseOrderShowFeature.includes("document.getElementById('ownerProductForm')")
    || !purchaseOrderShowFeature.includes('closeOwnerProductModal();\n                if (typeof Swal')
    || !purchaseOrderShowFeature.includes("window.location.reload()")) {
    failures.push('purchase-order receipt: owner-product dialog must keep unit-aware sale fields, conditional category selection, and close-on-save');
}

if (!purchaseOrderShowView.includes('اعتماد الطلبية')
    || !purchaseOrderShowView.includes("$isInventoryApproval = ! $isAccountantContext")
    || !purchaseOrderShowView.includes('$isFocusedOwnerWorkflow = $isOwnerReceiptReview || $isInventoryApproval')
    || !purchaseOrderShowView.includes('الكمية الآن')
    || !purchaseOrderShowView.includes('الكمية المستلمة')
    || purchaseOrderShowView.includes('المخزون وقت تأكيد الجرد')
    || !purchaseOrderShowView.includes('التكلفة التي ستعتمد')
    || !purchaseOrderShowFeature.includes("confirmButtonText: 'تأكيد الاعتماد'")
    || !purchaseOrderShowFeature.includes('لا يمكن التراجع عن هذه الخطوة')) {
    failures.push('purchase-order approval: focused stock-impact review and irreversible confirmation must remain');
}
if (purchaseOrderShowView.includes('حالة الجرد:')
    || purchaseOrderShowView.includes('عودة للقائمة')
    || !purchaseOrderShowView.includes('<x-ui.help title="ملخص البنود المستلمة"')) {
    failures.push('purchase-order approval: redundant legend and long inline guidance must not return');
}

for (const [viewPath, contract, moduleImport] of [
    ['resources/views/cashier/internal-use/create.blade.php', 'data-internal-use-config=', "import './features/cashier/internal-use-interface';"],
    ['resources/views/cashier/quick-sale/partials/tint-modal.blade.php', 'data-tint-sale-config=', "import './features/cashier/tint-sale-interface';"],
    ['resources/views/cashier/quick-sale/index.blade.php', 'data-quick-sale-config=', "import './features/cashier/quick-sale-interface';"],
]) {
    const source = fs.readFileSync(viewPath, 'utf8');
    if (source.includes('<script>') || !source.includes(contract) || !applicationEntry.includes(moduleImport)) {
        failures.push(`${viewPath}: stock-sensitive interface must remain extracted behind its config contract`);
    }
}
const employeeDebtComponent = fs.readFileSync('resources/views/components/employee/debt-form.blade.php', 'utf8');
if (employeeDebtComponent.includes('<script>')
    || !employeeDebtComponent.includes('data-employee-debt-interface')
    || !employeeDebtComponent.includes('data-employee-debt-collect')
    || !applicationEntry.includes("import './features/employees/debt-interface';")) {
    failures.push('employee debt component: collection behavior must remain extracted behind data contracts');
}
for (const [viewPath, contract, moduleImport] of [
    ['resources/views/user/stores/products/create.blade.php', 'data-product-create-interface', "import './features/store-products/product-create-interface';"],
    ['resources/views/user/stores/products/edit.blade.php', 'data-product-edit-interface', "import './features/store-products/product-edit-interface';"],
]) {
    const source = fs.readFileSync(viewPath, 'utf8');
    if (source.includes('<script>') || !source.includes(contract) || !applicationEntry.includes(moduleImport)) {
        failures.push(`${viewPath}: product unit behavior must remain extracted behind its activation contract`);
    }
}

for (const [name, view] of Object.entries({
    absence: accountantAbsenceView,
    withdrawal: accountantWithdrawalView,
    collection: accountantCollectionView,
    debt: accountantDebtView,
})) {
    if (!view.includes('data-ui-filter-input="employeesList"') || !view.includes('data-ui-filter-value=')) {
        failures.push(`accountant ${name}: employee filtering must use ui-actions`);
    }
}
if (!accountantAbsenceView.includes('data-ui-single-submit')
    || !accountantAbsenceView.includes('data-ui-scroll-lock')
    || accountantAbsenceView.includes('openAbsenceModal(')
    || accountantAbsenceView.includes('closeAbsenceModal(')) {
    failures.push('accountant absence: modal and single-submit actions must use ui-actions');
}
if (!accountantWithdrawalView.includes('data-ui-single-submit')
    || !accountantWithdrawalView.includes('data-ui-fill="id"')
    || accountantWithdrawalView.includes('openWithdrawalModal(')
    || accountantWithdrawalView.includes('closeWithdrawalModal(')) {
    failures.push('accountant withdrawal: modal and single-submit actions must use ui-actions');
}

// إصلاح مطبق: تغطي العقود أربع واجهات تنقل وتصفية إضافية.
const ownerDashboardView = fs.readFileSync('resources/views/dashboard/user/index.blade.php', 'utf8');
const accountantProductSearchView = fs.readFileSync('resources/views/accountants/pos/searchProduct.blade.php', 'utf8');
const employeeOperationModalView = fs.readFileSync('resources/views/components/employee/operation-details-modal.blade.php', 'utf8');
const purchaseOrderIndexView = fs.readFileSync('resources/views/modules/purchase-orders/user/index.blade.php', 'utf8');

if (!ownerDashboardView.includes('data-ui-submit-on-change')
    || ownerDashboardView.includes('onchange="this.form.submit()"')) {
    failures.push('owner dashboard: date filter must use ui-actions submit-on-change');
}
if (!accountantProductSearchView.includes('data-ui-toggle-class="rotate-180"')
    || accountantProductSearchView.includes('toggleDetails(')) {
    failures.push('accountant product search: details toggle must use ui-actions');
}
if (!employeeOperationModalView.includes('data-ui-hide="{{ $modalId }}"')) {
    failures.push('employee operation details: close action must use ui-actions');
}
if (!purchaseOrderIndexView.includes('data-ui-set-value="currentStatus"')
    || !purchaseOrderIndexView.includes('data-ui-submit-form="filterForm"')
    || purchaseOrderIndexView.includes('applyStatus(')) {
    failures.push('purchase order index: status filters must use ui-actions');
}

// إصلاح مطبق: تحمي العقود منتقي مستلمي الإشعار المشترك وتأكيدات أوامر الشراء.
const adminNotificationSendView = fs.readFileSync('resources/views/admin/notifications/send.blade.php', 'utf8');
const adminNotificationPushView = fs.readFileSync('resources/views/admin/notifications/push.blade.php', 'utf8');
const adminNotificationRecipientsFeature = fs.readFileSync('resources/js/features/admin-notification-recipients.js', 'utf8');

for (const [name, view] of Object.entries({ internal: adminNotificationSendView, push: adminNotificationPushView })) {
    if (!view.includes('data-admin-notification-recipients')
        || !view.includes('data-target-ids')
        || view.includes('<script>')
        || view.includes('onclick=')) {
        failures.push(`admin notification ${name}: recipient picker must use the shared safe feature`);
    }
}
if (!appEntry.includes("./features/admin-notification-recipients")
    || !adminNotificationRecipientsFeature.includes('selectedContainer.replaceChildren()')
    || adminNotificationRecipientsFeature.includes('.innerHTML')) {
    failures.push('admin notification recipients: safe shared feature is not fully wired');
}
if (!purchaseOrderIndexView.includes('data-ui-confirm-busy="انتظر..."')
    || purchaseOrderIndexView.includes('handleSecureSubmit(')
    || !sharedDialogsFeature.includes("hasAttribute('data-ui-confirm-busy')")) {
    failures.push('purchase order index: action confirmations and busy state must use ui-dialogs');
}

const rulesFor = (selector, allowSelectorList = false) => {
    const rules = [];
    root.walkRules((rule) => {
        const matches = allowSelectorList
            ? rule.selectors?.includes(selector)
            : rule.selector?.trim() === selector;
        if (matches) rules.push(rule);
    });
    return rules;
};

const declarationMap = (rule) => {
    const declarations = new Map();
    rule.walkDecls((declaration) => declarations.set(declaration.prop, declaration.value));
    return declarations;
};

const expectSingleRule = (selector, expectedDeclarations = {}, allowSelectorList = false) => {
    const rules = rulesFor(selector, allowSelectorList);
    if (rules.length !== 1) {
        failures.push(`${selector}: expected one definition, found ${rules.length}`);
        return;
    }

    const declarations = declarationMap(rules[0]);
    for (const [property, value] of Object.entries(expectedDeclarations)) {
        if (declarations.get(property) !== value) {
            failures.push(`${selector}: expected ${property}: ${value}`);
        }
    }
};

const darkTokens = {
    '--ui-brand': '#00C4B4',
    '--ui-bg': '#020617',
    '--ui-surface': '#0f172a',
    '--ui-text': '#f8fafc',
    '--ui-success-text': '#6ee7b7',
    '--ui-warning-text': '#f59e0b',
    '--ui-danger-text': '#ef4444',
    '--ui-info-text': '#22d3ee',
    '--ui-dot-success': '#22c55e',
    '--ui-dot-warning': '#facc15',
    '--ui-dot-danger': '#ef4444',
};

expectSingleRule(':root', darkTokens, true);
expectSingleRule('html.dark', darkTokens, true);
expectSingleRule('html.light', {
    '--ui-brand': '#00C4B4',
    '--ui-bg': '#f3f6fb',
    '--ui-surface': '#ffffff',
    '--ui-text': '#0f172a',
    '--ui-text-muted': '#475569',
    '--ui-success-bg': '#dcfce7',
    '--ui-success-text': '#166534',
    '--ui-warning-bg': 'var(--ui-surface-strong)',
    '--ui-warning-text': '#92400e',
    '--ui-danger-bg': '#fee2e2',
    '--ui-danger-text': '#b91c1c',
    '--ui-info-text': '#1e3a8a',
    '--ui-dot-success': '#22c55e',
    '--ui-dot-warning': '#facc15',
    '--ui-dot-danger': '#ef4444',
});
expectSingleRule('.ui-btn', {
    display: 'inline-flex',
    'align-items': 'center',
    'justify-content': 'center',
});
expectSingleRule('.ui-btn-primary', { 'background-color': 'var(--ui-brand)' });
expectSingleRule('.ui-btn-secondary', { 'background-color': 'var(--ui-surface-strong)' });
expectSingleRule('.ui-btn-info', { 'background-color': 'var(--ui-info-text)' });
expectSingleRule('.ui-btn-success', { 'background-color': 'var(--ui-success-text)' });
expectSingleRule('.ui-btn-warning', { 'background-color': 'var(--ui-warning-text)' });
expectSingleRule('.ui-btn-danger', { 'background-color': 'var(--ui-danger-text)' });
expectSingleRule('.ui-warning-help-btn', {
    display: 'inline-grid',
    'background': 'var(--ui-warning-bg)',
    color: 'var(--ui-warning-text)',
});
expectSingleRule('.ui-payment-option', {
    'border-color': 'var(--ui-border)',
    background: 'var(--ui-surface-muted)',
});
expectSingleRule('.ui-modal-backdrop', {
    display: 'flex',
    'align-items': 'center',
    'justify-content': 'center',
    'background-color': 'rgba(0, 0, 0, .7)',
});
expectSingleRule('.ui-modal-panel', {
    'border-radius': '1rem',
    'background-color': 'var(--ui-surface-muted)',
    'box-shadow': '0 25px 50px -12px rgba(0, 0, 0, .25)',
});
expectSingleRule('.ui-modal-panel-wide', { width: 'min(100%, 64rem)' });
expectSingleRule('.ui-modal-close-danger', {
    display: 'inline-flex',
    'align-items': 'center',
    'justify-content': 'center',
    width: '2.25rem',
    height: '2.25rem',
    background: 'var(--ui-surface-muted)',
    color: 'var(--ui-text-muted)',
});
expectSingleRule('.ui-modal-close-text-danger', {
    display: 'inline-flex',
    'align-items': 'center',
    'justify-content': 'center',
    background: 'var(--ui-danger-text)',
});
expectSingleRule('.ui-modal-header', {
    background: 'transparent',
    'border-bottom': '1px solid var(--ui-border)',
});
expectSingleRule('.ui-text-caption', { 'font-size': '.875rem' });
expectSingleRule('.ui-text-meta', { 'font-size': '.9375rem' });
expectSingleRule('.ui-text-body', { 'font-size': '1rem' });
expectSingleRule('.ui-inline-frame', {
    'align-items': 'center',
    'justify-content': 'space-between',
    border: '1px solid var(--ui-border)',
    padding: '.75rem 1rem',
}, true);
expectSingleRule('.ui-progress::-webkit-progress-value', {
    'background-color': 'var(--ui-brand)',
});
expectSingleRule('.ui-tooltip-popover', {
    'max-width': 'min(20rem, calc(100vw - 2rem))',
    'white-space': 'normal',
    'text-align': 'center',
});

const forbiddenPatterns = [
    [/\b(?:pink|rose|purple|fuchsia|orange)\b/i, 'forbidden color family'],
    [/#3b82f6\b/i, 'legacy blue progress color'],
    [/Tajawal/i, 'unapproved font'],
];

for (const [pattern, label] of forbiddenPatterns) {
    if (pattern.test(css)) failures.push(`${label}: ${pattern}`);
}

if (/\.ui-owner-/.test(css)) {
    failures.push('owner navbar responsive layout must use existing components and layout utilities without owner-specific app.css rules');
}

const componentSelectorCounts = new Map();
root.walkRules((rule) => {
    const insideResponsiveRule = rule.parent?.type === 'atrule'
        && ['media', 'supports'].includes(rule.parent.name);
    if (insideResponsiveRule || !rule.selector?.includes('.ui-')) return;

    componentSelectorCounts.set(
        rule.selector,
        (componentSelectorCounts.get(rule.selector) ?? 0) + 1,
    );
});

for (const [selector, count] of componentSelectorCounts) {
    if (count > 1) failures.push(`${selector}: duplicated ${count} times outside responsive rules`);
}

const collectBladeFiles = (directory) => fs.readdirSync(directory, { withFileTypes: true })
    .flatMap((entry) => {
        const path = `${directory}/${entry.name}`;
        if (entry.isDirectory()) return collectBladeFiles(path);
        return entry.isFile() && entry.name.endsWith('.blade.php') ? [path] : [];
    });

const accountantBladeFiles = [
    ...collectBladeFiles('resources/views/dashboard/accountant'),
    ...collectBladeFiles('resources/views/accountants'),
    ...collectBladeFiles('resources/views/components/employee'),
    'resources/views/dashboard/navbars/accountant.blade.php',
    'resources/views/dashboard/app.blade.php',
];

const bladeForbiddenPatterns = [
    [/<style\b/i, 'local style block'],
    [/\sstyle\s*=/i, 'inline style attribute'],
    [/@(?:push|section)\(['"]styles/i, 'local Blade styles section'],
    [/\.style\./, 'direct JavaScript style mutation'],
    [/text-\[(?:7|8|9|10|11|12)px\]|\btext-xs\b/, 'text below the approved readable scale'],
    [/(?:bg|text|border|from|to|ring|divide)-(?:pink|rose|purple|fuchsia|orange)(?:-|\/|\b)/i, 'forbidden direct color class'],
];

for (const file of accountantBladeFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="[^"]*ui-modal-close-danger[^"]*"[^>]*>/gis)) {
        if (!/aria-label="إغلاق"/.test(match[0])) failures.push(`${file}: modal close button needs aria-label`);
        if (!/type="button"/.test(match[0])) failures.push(`${file}: modal close button needs type="button"`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*>[\s\S]{0,180}?(معاينة|جزئي|كامل|إلغاء)[\s\S]{0,80}?<\/button>/gi)) {
        const classes = match[1];
        const role = match[2];
        const requiredClass = {
            'معاينة': 'ui-btn-info',
            'جزئي': 'ui-btn-warning',
            'كامل': 'ui-btn-success',
            'إلغاء': 'ui-btn-danger',
        }[role];
        if (!classes.includes(requiredClass)) failures.push(`${file}: ${role} button must use ${requiredClass}`);
    }
}

const ownerBladeFiles = [
    ...collectBladeFiles('resources/views/dashboard/user'),
    ...collectBladeFiles('resources/views/user'),
];

const adminBladeFiles = collectBladeFiles('resources/views/admin');

for (const file of ownerBladeFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="[^"]*ui-modal-close-danger[^"]*"[^>]*>/gis)) {
        if (!/aria-label="إغلاق"/.test(match[0])) failures.push(`${file}: modal close button needs aria-label`);
        if (!/type="button"/.test(match[0])) failures.push(`${file}: modal close button needs type="button"`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*>[\s\S]{0,180}?(إلغاء|حذف|رفض)[\s\S]{0,80}?<\/button>/gi)) {
        if (!match[1].includes('ui-btn-danger')) {
            failures.push(`${file}: ${match[2]} button must use ui-btn-danger`);
        }
    }
}

for (const file of adminBladeFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*>[\s\S]{0,180}?(إلغاء|حذف)[\s\S]{0,80}?<\/button>/gi)) {
        if (!match[1].includes('ui-btn-danger')) {
            failures.push(`${file}: ${match[2]} button must use ui-btn-danger`);
        }
    }
}

const modalScopeFiles = [
    ...collectBladeFiles('resources/views/dashboard/accountant'),
    ...collectBladeFiles('resources/views/accountants'),
    ...collectBladeFiles('resources/views/components/employee'),
];

for (const file of modalScopeFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    if (/sticky top-0[^"']*(?:ui-modal|ui-input-bg|ui-border-bottom)/.test(blade)) {
        failures.push(`${file}: modal header must not be sticky or independently surfaced`);
    }
}

const blankMixedInputFiles = [
    'resources/views/accountants/pos/collection.blade.php',
    'resources/views/accountants/pos/debt.blade.php',
    'resources/views/components/employee/debt-form.blade.php',
];

for (const file of blankMixedInputFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const match of blade.matchAll(/<input\b[^>]*id="[^"]*(?:CashAmount|CardAmount)"[^>]*>/gi)) {
        if (/\svalue=|\splaceholder=/.test(match[0])) {
            failures.push(`${file}: mixed cash/card inputs must start completely blank`);
        }
    }
}

// بعد الاستخراج أصبح نموذج الدفع في الوحدة، لذلك يفحص العقد المصدر التنفيذي بدل قالب Blade.
const quickSaleInterface = fs.readFileSync('resources/js/features/cashier/quick-sale-interface.js', 'utf8');
if (!/mixedCash:\s*''/.test(quickSaleInterface) || !/mixedCard:\s*''/.test(quickSaleInterface)) {
    failures.push('resources/js/features/cashier/quick-sale-interface.js: mixed payment models must start blank');
}

const singleColumnOperationFiles = [
    'resources/views/accountants/pos/absence.blade.php',
    'resources/views/accountants/pos/withdrawals.blade.php',
    'resources/views/accountants/pos/debt.blade.php',
    'resources/views/accountants/pos/collection.blade.php',
    ...collectBladeFiles('resources/views/components/employee'),
];

for (const file of singleColumnOperationFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    if (/(?:sm|md|lg|xl):grid-cols-(?:2|3|4|\[)/.test(blade)) {
        failures.push(`${file}: employee operation layout must remain single-column`);
    }
}

const employeeOperationViews = [
    'resources/views/accountants/pos/absence.blade.php',
    'resources/views/accountants/pos/withdrawals.blade.php',
    'resources/views/accountants/pos/debt.blade.php',
    'resources/views/accountants/pos/collection.blade.php',
];

for (const file of employeeOperationViews) {
    const blade = fs.readFileSync(file, 'utf8');
    if (/debt-payment-option|collection-payment-option|partial-payment-option/.test(blade)) {
        failures.push(`${file}: payment choices must use the shared ui-payment-option`);
    }
    if (/>\s*(?:محاسب|موظف)\s*</.test(blade) || /\?\s*'محاسب'\s*:\s*'موظف'/.test(blade)) {
        failures.push(`${file}: employee/accountant role decoration must not be shown on operation cards`);
    }
}

const accountantDashboard = fs.readFileSync('resources/views/dashboard/accountant/index.blade.php', 'utf8');
if (!accountantDashboard.includes('ui-responsive-table') || !accountantDashboard.includes('data-label="المبلغ"')) {
    failures.push('resources/views/dashboard/accountant/index.blade.php: latest operations must use the responsive table contract');
}
if (!accountantDashboard.includes('@click.stop data-ui-help-title="تفاصيل العمليات"')
    || !accountantDashboard.includes('class="ui-modal-panel ui-modal-panel-wide"')
    || !accountantDashboard.includes('class="ui-modal-close-text-danger">إغلاق</button>')
    || accountantDashboard.includes('ui-balance-modal-shell')
    || accountantDashboard.includes('ui-balance-modal-panel')) {
    failures.push('accountant dashboard: operations and balance dialogs must use the transfer-reference modal shell without nested help closing the parent');
}
const accountantBalanceConfirmation = fs.readFileSync('resources/js/features/accountant/balance-confirmation.js', 'utf8');
if (!accountantBalanceConfirmation.includes("form.closest('.ui-modal-backdrop')")
    || !accountantBalanceConfirmation.includes("classList.add('ui-modal-suspended')")
    || !accountantBalanceConfirmation.includes('restoreBalanceDialog()')
    || !css.includes('.ui-modal-suspended')) {
    failures.push('accountant balance: reference-day confirmation must suspend the underlying close dialog until the top confirmation resolves');
}

const invoiceInterfaceFiles = [
    'resources/views/invoices/create.blade.php',
    'resources/views/invoices/edit.blade.php',
    'resources/views/invoices/show.blade.php',
    'resources/views/invoices/index.blade.php',
];

for (const file of invoiceInterfaceFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }

    for (const match of blade.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*>[\s\S]{0,100}?(?:حذف|✕)[\s\S]{0,40}?<\/button>/gi)) {
        if (!match[1].includes('ui-btn-danger')) failures.push(`${file}: invoice delete controls must use ui-btn-danger`);
    }
}

const internalUseInterfaceFiles = [
    'resources/views/cashier/internal-use/create.blade.php',
    'resources/views/cashier/internal-use/report.blade.php',
    'resources/views/cashier/internal-use/trash.blade.php',
];

for (const file of internalUseInterfaceFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }
}

const internalUseReport = fs.readFileSync('resources/views/cashier/internal-use/report.blade.php', 'utf8');
for (const match of internalUseReport.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*>[\s\S]{0,100}?(?:حذف|حذف واسترجاع)[\s\S]{0,40}?<\/button>/gi)) {
    if (!match[1].includes('ui-btn-danger')) failures.push('internal-use report delete controls must use ui-btn-danger');
}

const broadRolloutFiles = [
    ...collectBladeFiles('resources/views/employees'),
    ...collectBladeFiles('resources/views/notifications'),
    ...collectBladeFiles('resources/views/subscriptions'),
    ...collectBladeFiles('resources/views/modules/purchase-orders/user'),
    'resources/views/cashier/quick-sale/index.blade.php',
    'resources/views/cashier/quick-sale/invoice-create.blade.php',
    'resources/views/cashier/quick-sale/partials/tint-modal.blade.php',
    'resources/views/components/stat-card.blade.php',
    'resources/views/dashboard/footer.blade.php',
    'resources/views/dashboard/navbars/user.blade.php',
    'resources/views/dashboard/sidebars/admin.blade.php',
];

for (const file of broadRolloutFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    for (const [pattern, label] of bladeForbiddenPatterns) {
        if (pattern.test(blade)) failures.push(`${file}: ${label}`);
    }
}

const employeeIndex = fs.readFileSync('resources/views/employees/index.blade.php', 'utf8');
for (const match of employeeIndex.matchAll(/<button\b[^>]*class="([^"]*)"[^>]*(?:title|aria-label)="(?:حذف|إيقاف الموظف)"[^>]*>/gi)) {
    if (!match[1].includes('ui-btn-danger')) failures.push('employee destructive controls must use ui-btn-danger');
}

const printIdentityFiles = [
    'resources/views/cashier/quick-sale/invoice-print.blade.php',
    'resources/views/invoices/print.blade.php',
    'resources/views/modules/purchase-orders/pdf.blade.php',
    ...collectBladeFiles('resources/views/pdf'),
];

const printStyleDeclarations = (blade, file) => {
    const declarations = [];

    for (const styleBlock of blade.matchAll(/<style\b[^>]*>([\s\S]*?)<\/style>/gi)) {
        const printStyleRoot = postcss.parse(styleBlock[1], { from: file });
        printStyleRoot.walkDecls((declaration) => declarations.push(declaration));
    }

    for (const inlineStyle of blade.matchAll(/\sstyle\s*=\s*["']([^"']*)["']/gi)) {
        const inlineStyleRoot = postcss.parse(`print-element { ${inlineStyle[1]} }`, { from: file });
        inlineStyleRoot.walkDecls((declaration) => declarations.push(declaration));
    }

    return declarations;
};

for (const file of printIdentityFiles) {
    const blade = fs.readFileSync(file, 'utf8');
    const printDeclarations = printStyleDeclarations(blade, file);
    const primaryPrintFonts = printDeclarations
        .filter((declaration) => declaration.prop.toLowerCase() === 'font-family')
        .map((declaration) => declaration.value.split(',')[0].trim().replace(/^['"]|['"]$/g, '').toLowerCase());
    if (!primaryPrintFonts.includes('cairo')) {
        failures.push(`${file}: print identity must prefer Cairo`);
    }
    for (const fontSizeDeclaration of printDeclarations.filter((declaration) => declaration.prop.toLowerCase() === 'font-size')) {
        const pixelFontSize = fontSizeDeclaration.value.match(/^([0-9]+(?:\.[0-9]+)?)px$/i);
        if (pixelFontSize && Number(pixelFontSize[1]) < 10) {
            failures.push(`${file}: printed text must not be smaller than 10px`);
            break;
        }
    }
    if (/(?:pink|rose|purple|fuchsia|orange)|#(?:7c3aed|f3e8ff|6b21a8|d8b4fe|ec4899|db2777|fdf2f8|fb7185|f43f5e)/i.test(blade)) failures.push(`${file}: forbidden color in print identity`);
}

const internalUsePdf = fs.readFileSync('resources/views/pdf/internal_use_pdf.blade.php', 'utf8');
if (/\sstyle="/i.test(internalUsePdf)) failures.push('internal_use_pdf: repeated inline declarations must remain consolidated');

const resetEmail = fs.readFileSync('resources/views/emails/reset.blade.php', 'utf8');
if (!resetEmail.includes("font-family:'Cairo'") || !resetEmail.includes('#00C4B4')) {
    failures.push('reset email must use Cairo and the approved brand color');
}

const debtCollection = fs.readFileSync('resources/views/accountants/pos/debt.blade.php', 'utf8');
const creditCollection = fs.readFileSync('resources/views/accountants/pos/collection.blade.php', 'utf8');
// أصبح حوار دفع الآجل في وحدة مستقلة، لذلك يفحص العقد مصدر الواجهة ومصدر السلوك معًا.
const creditCollectionModule = fs.readFileSync('resources/js/features/accountant/credit-collection.js', 'utf8');
const debtCollectionModule = fs.readFileSync('resources/js/features/accountant/debt-operations.js', 'utf8');
for (const paymentLayoutClass of ['text-right space-y-4', 'rounded-2xl border ui-border ui-input-bg p-3']) {
    if (!(debtCollection + debtCollectionModule).includes(paymentLayoutClass)
        || !(creditCollection + creditCollectionModule).includes(paymentLayoutClass)) {
        failures.push(`debt and credit collection payment dialogs must share ${paymentLayoutClass}`);
    }
}

const expensePage = fs.readFileSync('resources/views/accountants/pos/expense.blade.php', 'utf8');
if (!expensePage.includes('grid grid-cols-2 gap-3 sm:gap-4')) {
    failures.push('expense summary cards must remain on one row');
}
const ownerRoutes = fs.readFileSync('routes/user.php', 'utf8');
const ownerNavigationComposer = fs.readFileSync('app/View/Composers/DashboardNavigationComposer.php', 'utf8');
const ownerNavigationView = fs.readFileSync('resources/views/dashboard/navbars/user.blade.php', 'utf8');
for (const ownerExpenseContract of [
    "Route::prefix('expenses')->name('expenses.')",
    "route('user.stores.expenses.index'",
    "route('user.stores.expenses.store'",
    "route('user.stores.expenses.update'",
    "'user.stores.expenses.destroy'",
    'name="date"',
    'name="business_date"',
    'اليوم',
    'title="فلترة المصروفات"',
    'ui-modal-panel ui-modal-panel-transfer',
]) {
    if (!(ownerRoutes + ownerNavigationComposer + ownerNavigationView + expensePage).includes(ownerExpenseContract)) {
        failures.push(`owner expenses must preserve: ${ownerExpenseContract}`);
    }
}
if (expensePage.includes('ui-modal-close-danger')) {
    failures.push('owner/accountant expense dialogs must use the centralized text close action');
}
const expenseController = fs.readFileSync('app/Http/Controllers/Store/ExpenseController.php', 'utf8');
if (!expenseController.includes("->forAccountingDate($selectedAccountingDate)")
    || !expenseController.includes("$shiftContext['business_date']")
    || !expenseController.includes("$validated['business_date'] ?? $shiftContext['business_date']")
    || !expenseController.includes("'selectedAccountingDate' => $selectedAccountingDate")) {
    failures.push('owner expenses: creation default and day filter must remain anchored to the accounting business date');
}
if (!css.includes('--ui-text-soft: #1e293b;')) {
    failures.push('light theme: secondary operational text and field labels must keep the strengthened contrast token');
}

const ownerDashboard = fs.readFileSync('resources/views/dashboard/user/index.blade.php', 'utf8');
const ownerDashboardModule = fs.readFileSync('resources/js/features/dashboard/owner-dashboard.js', 'utf8');
for (const ownerDashboardContract of [
    'تفاصيل الراتب',
    'ui-responsive-table w-full',
    'data-label="خصم الغياب"',
    'md:col-span-2 xl:col-span-4',
    'class="ui-modal-close-text-danger">إغلاق</button>',
]) {
    if (!(ownerDashboard + ownerDashboardModule).includes(ownerDashboardContract)) {
        failures.push(`owner dashboard must preserve: ${ownerDashboardContract}`);
    }
}

if (!ownerDashboard.includes('id="metric-modal" class="ui-modal-backdrop hidden')
    || !ownerDashboard.includes('id="salary-withdrawals-modal" class="ui-modal-backdrop hidden')
    || (ownerDashboard.match(/ui-modal-panel-transfer/g) ?? []).length < 3
    || ownerDashboard.includes('ui-modal-close-danger')
    || !css.includes('.ui-modal-panel-transfer')) {
    failures.push('owner dashboard: monthly metric and salary dialogs must use the transfer-reference modal shell');
}

const ownerNavigation = fs.readFileSync('resources/views/dashboard/navbars/user.blade.php', 'utf8');
const centeredMobileOwnerDropdown = 'fixed inset-x-3 top-16 z-[60] mx-auto py-3 ui-dropdown-panel sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:mx-0';
if (!ownerNavigation.includes('ui-brand-word')
    || (ownerNavigation.split(centeredMobileOwnerDropdown).length - 1) < 2
    || !ownerNavigation.includes('ui-card flex items-center justify-between gap-3 p-3 sm:hidden')
    || !ownerNavigation.includes('class="relative hidden sm:block"')
    || !ownerNavigation.includes('aria-label="تسجيل الخروج"')
    || (ownerNavigation.match(/>متاجري</g) ?? []).length < 3) {
    failures.push('owner navigation must keep the brand and stores label visible, center both mobile store panels, and place the mobile account in the menu');
}

const unifiedEmployeeIndex = fs.readFileSync('resources/views/employees/index.blade.php', 'utf8');
if (!unifiedEmployeeIndex.includes('$person->activeAccountant') || !unifiedEmployeeIndex.includes('aria-label="محاسب فعال"') || unifiedEmployeeIndex.includes('راتب الشهر المتوقع')) {
    failures.push('employees index must remain the unified employee/accountant interface');
}

const employeeActions = fs.readFileSync('resources/views/employees/actions.blade.php', 'utf8');
for (const employeeRoleContract of ['الجهة الوظيفية', 'محاسب فعال', 'محاسب موقوف', 'سحب الترقية']) {
    if (!employeeActions.includes(employeeRoleContract)) failures.push(`employee profile must preserve: ${employeeRoleContract}`);
}

const transferForm = fs.readFileSync('resources/views/components/store-transfer-form.blade.php', 'utf8');
const transferFeature = fs.readFileSync('resources/js/features/store-transfers/transfer-system.js', 'utf8');
for (const transferContract of ['items[${index}][sender_product_id]', 'إضافة منتج', "value: 'roll'", "value: 'meter'", "value: 'kit'", "value: 'piece'"]) {
    if (!`${transferForm}\n${transferFeature}`.includes(transferContract)) failures.push(`store transfer form must preserve: ${transferContract}`);
}
if (transferForm.includes('data-select-search') || transferForm.includes('ابحث باسم المتجر')) {
    failures.push('receiver store must use the owner stores dropdown without a second search field');
}

const storeForm = fs.readFileSync('resources/views/user/stores/includes/store-form.blade.php', 'utf8');
if (!storeForm.includes('name="labor_description_options[]"') || storeForm.includes('تحديث المعلومات المسجلة في النظام')) {
    failures.push('store edit must keep separate labor fields and the approved short wording');
}

const storeShow = fs.readFileSync('resources/views/user/stores/show.blade.php', 'utf8');
if (!storeShow.includes('title="طريقة حساب صافي النتيجة"') || !storeShow.includes('ui-frame-row px-4 py-3')) {
    failures.push('store page must preserve the calculation help dialog and unified operation rows');
}

const productFormFiles = {
    create: 'resources/views/user/stores/products/create.blade.php',
    edit: 'resources/views/user/stores/products/edit.blade.php',
};
const sharedProductFieldNames = [
    'category_id',
    'product_type',
    'usage_type',
    'is_splittable',
    'quick_sale_default_unit',
    'items_per_unit',
    'piece_price',
    'roll_length',
    'tint_manufacturer',
    'tint_size',
    'tint_grade',
    'tint_extra',
    'name',
    'price',
    'cost_price',
    'min_stock',
    'carton_qty',
    'waste_percentage',
    'description',
    'status',
    'image',
];

for (const productFormFile of Object.values(productFormFiles)) {
    const productForm = fs.readFileSync(productFormFile, 'utf8');
    const productInterfaceModule = fs.readFileSync(
        productFormFile === productFormFiles.create
            ? 'resources/js/features/store-products/product-create-interface.js'
            : 'resources/js/features/store-products/product-edit-interface.js',
        'utf8',
    );
    const productContractSource = productForm + productInterfaceModule;
    for (const responsiveProductContract of [
        'ui-product-form-page',
        'ui-product-form-header',
        'ui-product-form-card',
        'ui-product-splittable-grid',
        'ui-product-fraction-row',
    ]) {
        if (!productContractSource.includes(responsiveProductContract)) {
            failures.push(`${productFormFile}: product form must preserve ${responsiveProductContract}`);
        }
    }
    if (productForm.includes('<div class="w-32"></div>') || productForm.includes('class="flex gap-2 mb-2"')) {
        failures.push(`${productFormFile}: product form must not restore fixed mobile spacers or fraction rows`);
    }

    for (const fieldName of sharedProductFieldNames) {
        if (!productForm.includes(`name="${fieldName}"`)) {
            failures.push(`${productFormFile}: product request contract must preserve field ${fieldName}`);
        }
    }

    for (const fractionFieldName of ['option_label', 'deduction_value', 'price']) {
        if (!productContractSource.includes(`[${fractionFieldName}]"`)) {
            failures.push(`${productFormFile}: product request contract must preserve fractions[][${fractionFieldName}]`);
        }
    }
}

const createProductForm = fs.readFileSync(productFormFiles.create, 'utf8');
const createProductInterface = fs.readFileSync('resources/js/features/store-products/product-create-interface.js', 'utf8');
const editProductInterface = fs.readFileSync('resources/js/features/store-products/product-edit-interface.js', 'utf8');
for (const createFieldName of ['min_stock_storage_unit', 'quantity', 'num_rolls', 'stay_here']) {
    if (!createProductForm.includes(`name="${createFieldName}"`)) {
        failures.push(`${productFormFiles.create}: create request contract must preserve field ${createFieldName}`);
    }
}
if (!createProductForm.includes("route('user.stores.products.store', $store->id)")
    || !createProductForm.includes('method="POST"')) {
    failures.push(`${productFormFiles.create}: create request must preserve its store route and POST method`);
}

const editProductForm = fs.readFileSync(productFormFiles.edit, 'utf8');
if (!editProductForm.includes("route('user.stores.products.update', [$store->id, $product->id])")
    || !editProductForm.includes("@method('PUT')")) {
    failures.push(`${productFormFiles.edit}: update request must preserve its product route and PUT method`);
}

const sharedProductFormScriptPath = 'resources/js/features/store-products/product-form-shared.js';
const sharedProductFormScript = fs.readFileSync(sharedProductFormScriptPath, 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/store-products/product-form-shared';")) {
    failures.push('application script must load the shared store product form feature');
}
for (const productFormFile of Object.values(productFormFiles)) {
    const productForm = fs.readFileSync(productFormFile, 'utf8');
    if (!productForm.includes('data-store-product-form')) {
        failures.push(`${productFormFile}: product form must expose the shared feature root`);
    }
    if (/\son(?:click|change)=/i.test(productForm)) {
        failures.push(`${productFormFile}: product form interactions must use data hooks and registered listeners`);
    }
    if (productForm.includes('window.onload') || productForm.includes('function toggleSplittableFields(')) {
        failures.push(`${productFormFile}: shared initialization and splittable visibility must remain in the product feature`);
    }
    if (!productForm.includes('data-product-form-errors') || productForm.includes("Swal.fire({")) {
        failures.push(`${productFormFile}: validation errors must use the shared safe renderer`);
    }
    for (const sharedFunctionName of [
        'updateFractionalGuidance',
        'parseTintProductName',
        'initializeTintNameFields',
        'updateTintNamePreview',
        'disableNumberWheelInputs',
    ]) {
        if (productForm.includes(`function ${sharedFunctionName}(`)) {
            failures.push(`${productFormFile}: ${sharedFunctionName} must remain in the shared product form feature`);
        }
        if (!sharedProductFormScript.includes(`function ${sharedFunctionName}(`)) {
            failures.push(`${sharedProductFormScriptPath}: missing shared function ${sharedFunctionName}`);
        }
    }
}
for (const productDataHook of ['data-product-add-fraction', 'data-product-remove-fraction']) {
    if (!(createProductForm + createProductInterface).includes(productDataHook)
        || !(editProductForm + editProductInterface).includes(productDataHook)) {
        failures.push(`product forms must preserve shared interaction hook ${productDataHook}`);
    }
}
for (const productForm of [createProductForm, editProductForm]) {
    if (!productForm.includes('<x-ui.help')
        && !(productForm.includes('data-ui-help-title') && productForm.includes('data-ui-help-body'))) {
        failures.push('product forms must use the centralized help component or its data contract for help dialogs');
    }
}
if (sharedProductFormScript.includes('Object.assign(window')) {
    failures.push(`${sharedProductFormScriptPath}: shared product interactions must not expose temporary global functions`);
}
for (const sharedPresentationFunction of [
    'updateSplittableFieldsVisibility',
    'showProductFormValidationErrors',
]) {
    if (!sharedProductFormScript.includes(`function ${sharedPresentationFunction}(`)) {
        failures.push(`${sharedProductFormScriptPath}: missing shared presentation function ${sharedPresentationFunction}`);
    }
}
if (sharedProductFormScript.includes('messages.map(') || !sharedProductFormScript.includes('messageRow.textContent')) {
    failures.push(`${sharedProductFormScriptPath}: validation messages must render with safe DOM text`);
}

const productPriceHistoryView = fs.readFileSync('resources/views/user/stores/products/index.blade.php', 'utf8');
const employeeOperationDetailsModal = fs.readFileSync('resources/views/components/employee/operation-details-modal.blade.php', 'utf8');
if (!productPriceHistoryView.includes('data-price-history-close class="ui-modal-close-text-danger">إغلاق</button>')
    || !productPriceHistoryView.includes('<div class="ui-table-wrap">')) {
    failures.push('product price history: dialog must use the shared close action and table shell');
}
if (!employeeOperationDetailsModal.includes('class="ui-modal-panel ui-modal-panel-wide ui-modal-panel-transfer"')
    || !employeeOperationDetailsModal.includes('class="ui-help-modal-close"')) {
    failures.push('employee operation details: dialog must use the approved help-modal shell and close action');
}

const productCatalogFile = 'resources/views/user/stores/products/index.blade.php';
const productCatalog = fs.readFileSync(productCatalogFile, 'utf8');
const productCatalogScriptPath = 'resources/js/features/store-products/product-catalog.js';
const productCatalogScript = fs.readFileSync(productCatalogScriptPath, 'utf8');
if (!productCatalog.includes('$quickStockLabel = $product->product_type === \'fractional\'')
    || !productCatalog.includes('ProductQuantityFormatter::currentStock(')
    || !productCatalog.includes('ProductQuantityFormatter::stockSnapshot(')
    || !productCatalog.includes("'stock_label' => 'الكمية: ' . $quickStockLabel")) {
    failures.push('product quick cards: stock quantity must follow the product default sale unit');
}
if (productCatalog.includes('<script>') || /\sonclick=/i.test(productCatalog)) {
    failures.push(`${productCatalogFile}: catalog behavior must remain in the shared catalog feature`);
}
for (const catalogHook of [
    'data-store-products-catalog',
    'data-store-products-catalog-config',
    'data-product-details-toggle',
    'data-price-history-url',
    'data-price-history-close',
]) {
    if (!productCatalog.includes(catalogHook)) failures.push(`${productCatalogFile}: missing catalog hook ${catalogHook}`);
}
if (!productCatalog.includes('$productCatalogConfig = [')
    || !productCatalog.includes('@json($productCatalogConfig)')
    || /@json\(\[/.test(productCatalog)) {
    failures.push(`${productCatalogFile}: catalog JSON must use a prepared variable so Blade can compile it safely`);
}
if (!productCatalog.includes('<span>إضافة منتج</span>')
    || productCatalog.includes('<span class="hidden sm:inline">منتج جديد</span>')) {
    failures.push(`${productCatalogFile}: add-product action must keep its explanatory text visible on small screens`);
}
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/store-products/product-catalog';")) {
    failures.push('application script must load the store product catalog feature');
}
if (productCatalogScript.includes('innerHTML') || !productCatalogScript.includes('window.Alpine.data')) {
    failures.push(`${productCatalogScriptPath}: catalog must use Alpine registration and safe DOM rendering`);
}

const stockViewFile = 'resources/views/user/stores/products/stock/index.blade.php';
const stockView = fs.readFileSync(stockViewFile, 'utf8');
for (const stockRequestContract of [
    "route('user.stores.products.stock.audit-confirm'",
    "route('user.stores.products.stock.increase'",
    "route('user.stores.products.stock.decrease'",
    'name="audit_note"',
    'name="quantity"',
    'name="unit_type"',
    'name="note"',
    'data-disable-on-submit',
    'data-validate-available-stock',
]) {
    if (!stockView.includes(stockRequestContract)) failures.push(`${stockViewFile}: missing stock request contract ${stockRequestContract}`);
}
if (!stockView.includes("($inventoryAuditStatus['color'] ?? null) === 'green'")
    || !stockView.includes("($inventoryAuditStatus['confirmed_at'] ?? null)")
    || !stockView.includes('<x-ui.badge variant="success"')
    || !stockView.includes("$inventoryAuditStatus['confirmed_at']->format('Y-m-d')")
    || !stockView.includes('تم الجرد')) {
    failures.push(`${stockViewFile}: currently audited products must show the centralized completed-audit badge`);
}
const inventoryScriptPath = 'resources/js/features/store-products/inventory-system.js';
const inventoryScript = fs.readFileSync(inventoryScriptPath, 'utf8');
if (!inventoryScript.includes('normalizedRequestedQuantity')
    || !inventoryScript.includes('data-validate-available-stock')
    || !inventoryScript.includes('insufficientTitle')) {
    failures.push(`${inventoryScriptPath}: stock withdrawal must reject quantities above the available normalized balance`);
}
const inventoryAuditView = fs.readFileSync('resources/views/user/stores/products/audit.blade.php', 'utf8');
if (stockView.includes('<script>') || !stockView.includes('data-inventory-system') || !inventoryAuditView.includes('data-inventory-audit')) {
    failures.push('inventory views must expose their system roots and keep operational JavaScript outside Blade');
}
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/store-products/inventory-system';")
    || !inventoryScript.includes('window.Swal.fire')
    || !inventoryScript.includes('window.confirm')
    || !inventoryScript.includes("form.submit()")) {
    failures.push(`${inventoryScriptPath}: inventory confirmations and single-submit behavior must remain in the shared feature`);
}
const categoryViewFiles = [
    'resources/views/user/stores/categories/index.blade.php',
    'resources/views/user/stores/categories/create.blade.php',
    'resources/views/user/stores/categories/edit.blade.php',
    'resources/views/user/stores/categories/trash.blade.php',
];
for (const categoryViewFile of categoryViewFiles) {
    const categoryView = fs.readFileSync(categoryViewFile, 'utf8');
    if (!categoryView.includes('data-category-system') || categoryView.includes('<script>') || /\sonsubmit=/i.test(categoryView)) {
        failures.push(`${categoryViewFile}: category system must use its shared root without inline scripts or submit handlers`);
    }
}
for (const categoryFormViewFile of [
    'resources/views/user/stores/categories/create.blade.php',
    'resources/views/user/stores/categories/edit.blade.php',
    'resources/views/user/stores/categories/trash.blade.php',
]) {
    const categoryFormView = fs.readFileSync(categoryFormViewFile, 'utf8');
    if (!categoryFormView.includes('md:grid-cols-[auto_1fr_5rem]')
        || !categoryFormView.includes('hidden w-20 md:block')
        || !categoryFormView.includes('text-center text-2xl')) {
        failures.push(`${categoryFormViewFile}: category header must preserve the responsive centered layout`);
    }
}
const categoryIndexView = fs.readFileSync('resources/views/user/stores/categories/index.blade.php', 'utf8');
if (!categoryIndexView.includes('md:flex-row md:items-center md:justify-between')
    || !categoryIndexView.includes('grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto')) {
    failures.push('category index header and primary actions must remain responsive');
}
const categorySystemScriptPath = 'resources/js/features/store-categories/category-system.js';
const categorySystemScript = fs.readFileSync(categorySystemScriptPath, 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/store-categories/category-system';")
    || !categorySystemScript.includes('window.Swal.fire')
    || !categorySystemScript.includes('window.confirm')
    || !categorySystemScript.includes('category_name_preset')
    || !categorySystemScript.includes('target_store_id')) {
    failures.push(`${categorySystemScriptPath}: category presets and transfer confirmation must remain centralized`);
}
const storeManagementViewFiles = [
    'resources/views/user/stores/index.blade.php',
    'resources/views/user/stores/create.blade.php',
    'resources/views/user/stores/edit.blade.php',
    'resources/views/user/stores/trash.blade.php',
    'resources/views/user/stores/includes/store-form.blade.php',
];
for (const storeManagementViewFile of storeManagementViewFiles) {
    const storeManagementView = fs.readFileSync(storeManagementViewFile, 'utf8');
    const usesSharedForm = storeManagementViewFile.endsWith('/create.blade.php') || storeManagementViewFile.endsWith('/edit.blade.php');
    if ((!usesSharedForm && !storeManagementView.includes('data-store-management')) || /\sonclick=/i.test(storeManagementView)) {
        failures.push(`${storeManagementViewFile}: store management must use its shared root without inline click handlers`);
    }
}
const storeCreateView = fs.readFileSync('resources/views/user/stores/create.blade.php', 'utf8');
const dashboardNavigationScript = fs.readFileSync('resources/js/features/dashboard-navigation.js', 'utf8');
const storeFormView = fs.readFileSync('resources/views/user/stores/includes/store-form.blade.php', 'utf8');
if (!storeCreateView.includes("@include('user.stores.includes.store-form')")
    || !dashboardNavigationScript.includes('[data-history-back]')) {
    failures.push('store management must keep the create view and shared history navigation available');
}
if (storeFormView.includes("$isEdit ? ' ' : ' '") || /<div>\s*<div>\s*<form/s.test(storeFormView)) {
    failures.push('store management cleanup must not restore empty form wrappers or empty class expressions');
}
const cleanedSystemSources = [
    categorySystemScript,
    inventoryScript,
    ...categoryViewFiles.map((categoryViewFile) => fs.readFileSync(categoryViewFile, 'utf8')),
    fs.readFileSync(productFormFiles.create, 'utf8'),
    fs.readFileSync(productFormFiles.edit, 'utf8'),
].join('\n');
for (const [deadPattern, deadLabel] of [
    [/\bapplyingPreset\b/, 'unused category preset state'],
    [/\bcategory-preset-btn\b/, 'unused category preset class'],
    [/dataset\.originalText/, 'unused inventory button text cache'],
    [/\bcurrentUnitMode\b/, 'unused product unit mode state'],
    [/تم استعادته|الذكاء المضاف/, 'stale implementation comment'],
]) {
    if (deadPattern.test(cleanedSystemSources)) failures.push(`system cleanup: ${deadLabel} must not return`);
}
const uiDialogsScriptPath = 'resources/js/features/ui-dialogs.js';
const uiDialogsScript = fs.readFileSync(uiDialogsScriptPath, 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/ui-dialogs';")
    || !uiDialogsScript.includes('window.Swal.fire')
    || !uiDialogsScript.includes('window.confirm')) {
    failures.push(`${uiDialogsScriptPath}: SweetAlert must be primary and browser confirmation must remain fallback only`);
}
for (const productConfirmationFile of [productCatalogFile, 'resources/views/user/stores/products/trash.blade.php']) {
    const productConfirmationView = fs.readFileSync(productConfirmationFile, 'utf8');
    if (/\sonsubmit=|\bconfirm\s*\(/i.test(productConfirmationView) || !productConfirmationView.includes('data-ui-confirm')) {
        failures.push(`${productConfirmationFile}: product confirmations must use the shared SweetAlert contract`);
    }
}

const reportViewFiles = {
    index: 'resources/views/user/stores/reports/index.blade.php',
    recent: 'resources/views/user/stores/reports/last-ten-days.blade.php',
    monthly: 'resources/views/user/stores/reports/monthly.blade.php',
    employees: 'resources/views/user/stores/reports/employees-monthly.blade.php',
    search: 'resources/views/user/stores/reports/comprehensive-search.blade.php',
};
const reportViews = Object.fromEntries(
    Object.entries(reportViewFiles).map(([reportName, reportFile]) => [reportName, fs.readFileSync(reportFile, 'utf8')]),
);
for (const reportName of ['recent', 'monthly', 'employees']) {
    if (!reportViews[reportName].includes('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between')) {
        failures.push(`${reportViewFiles[reportName]}: report header must remain responsive on small screens`);
    }
}
if (!reportViews.monthly.includes('min-w-[900px]')
    || !reportViews.recent.includes('min-w-[640px]')
    || !reportViews.employees.includes('grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto')) {
    failures.push('store reports: tables and header actions must preserve their mobile overflow contract');
}
if (!reportViews.monthly.includes('ui-modal-panel ui-modal-panel-wide ui-modal-panel-transfer')
    || !reportViews.monthly.includes('ui-help-modal-header')
    || !reportViews.monthly.includes('ui-help-modal-close')
    || !reportViews.monthly.includes('aria-label="إغلاق نافذة')) {
    failures.push(`${reportViewFiles.monthly}: monthly report detail modals must use the help-modal visual shell with an accessible close button`);
}
if (!reportViews.search.includes('ui-modal-panel ui-modal-panel-wide')
    || !reportViews.search.includes('ui-btn ui-btn-danger')
    || !reportViews.search.includes('aria-label="إغلاق نافذة')) {
    failures.push(`${reportViewFiles.search}: report modals must stay viewport-bound with accessible danger close buttons`);
}

const userLogsViewFile = 'resources/views/user/logs/index.blade.php';
const userLogsView = fs.readFileSync(userLogsViewFile, 'utf8');
const userLogsFeatureFile = 'resources/js/features/user-logs/log-details.js';
const userLogsFeature = fs.readFileSync(userLogsFeatureFile, 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/user-logs/log-details';")
    || !userLogsView.includes('data-user-logs')
    || !userLogsView.includes('data-log-details-trigger')
    || userLogsView.includes('<script')
    || /\sonclick=/i.test(userLogsView)) {
    failures.push(`${userLogsViewFile}: log details must remain connected to the shared feature without inline scripts`);
}
if (!userLogsFeature.includes('textContent')
    || userLogsFeature.includes('innerHTML')
    || !userLogsFeature.includes('window.Swal.fire')
    || !userLogsFeature.includes('window.alert')) {
    failures.push(`${userLogsFeatureFile}: log details must use safe DOM rendering with a browser fallback`);
}
if (!userLogsView.includes('sm:grid-cols-2 lg:grid-cols-4')
    || !userLogsView.includes('min-w-[720px]')) {
    failures.push(`${userLogsViewFile}: filters and table must preserve their responsive layout`);
}

const transferSystemScriptPath = 'resources/js/features/store-transfers/transfer-system.js';
const transferSystemScript = fs.readFileSync(transferSystemScriptPath, 'utf8');
const transferFormView = fs.readFileSync('resources/views/components/store-transfer-form.blade.php', 'utf8');
const transferPickerView = fs.readFileSync('resources/views/components/store-transfers/product-picker.blade.php', 'utf8');
const transferIndexViewFiles = [
    'resources/views/user/store-transfers/index.blade.php',
    'resources/views/accountants/store-transfers/index.blade.php',
];
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/store-transfers/transfer-system';")
    || !transferSystemScript.includes("window.Alpine.data('storeTransferBuilder'")
    || !transferSystemScript.includes('[data-transfer-product-picker]')) {
    failures.push(`${transferSystemScriptPath}: transfer creation and receiver product picking must remain centralized`);
}
if (!transferFormView.includes('data-store-transfer-system')
    || transferFormView.includes('<script')
    || !transferPickerView.includes('name="receiver_product_id[{{ $item->id }}]"')) {
    failures.push('store transfers: shared form and product picker contracts must remain available');
}
for (const transferIndexViewFile of transferIndexViewFiles) {
    const transferIndexView = fs.readFileSync(transferIndexViewFile, 'utf8');
    if (!transferIndexView.includes('data-store-transfer-system')
        || !transferIndexView.includes('<x-store-transfers.product-picker')
        || transferIndexView.includes('<script')
        || /\sonsubmit=/i.test(transferIndexView)
        || !transferIndexView.includes('data-ui-confirm')) {
        failures.push(`${transferIndexViewFile}: transfer lists must use shared picker and confirmation features without inline scripts`);
    }
}

const authenticationLayoutFile = 'resources/views/layouts/auth.blade.php';
const authenticationLayout = fs.readFileSync(authenticationLayoutFile, 'utf8');
const fontReadinessFeature = fs.readFileSync('resources/js/features/font-readiness.js', 'utf8');
const cairoLocalFontFiles = [
    'Cairo-Regular.ttf',
    'Cairo-SemiBold.ttf',
    'Cairo-Bold.ttf',
    'Cairo-Black.ttf',
];
const cairoArabicFontPath = 'fonts/cairo/cairo-arabic-wght-normal.woff2';
const authenticationViewFiles = [
    'resources/views/auth/login.blade.php',
    'resources/views/auth/forgot.blade.php',
    'resources/views/auth/reset.blade.php',
    'resources/views/auth/suspended.blade.php',
    'resources/views/auth/welcome-screen.blade.php',
];
const authenticationFieldLabels = {
    'resources/views/auth/login.blade.php': [
        ['login-email', 'email'],
        ['login-password', 'password'],
    ],
    'resources/views/auth/forgot.blade.php': [
        ['forgot-email', 'email'],
    ],
    'resources/views/auth/reset.blade.php': [
        ['reset-password', 'password'],
        ['reset-password-confirmation', 'password_confirmation'],
    ],
};
if (/cdn[^"']*alpinejs|alpinejs[^"']*cdn/i.test(authenticationLayout)
    || !authenticationLayout.includes("'resources/js/app.js'")
    || !authenticationLayout.includes('<html lang="ar" dir="rtl" class="dark ui-font-loading">')
    || !authenticationLayout.includes('<body class="auth-page auth-page-center">')
    || !authenticationLayout.includes(`asset('${cairoArabicFontPath}')`)
    || !/\.auth-page\s*\{[\s\S]*?min-height:\s*100dvh;/.test(css)
    || authenticationLayout.includes('<div class="auth-shell">')) {
    failures.push(`${authenticationLayoutFile}: authentication must use the single application entry without a duplicate visual shell`);
}
if (!fontReadinessFeature.includes("document.fonts.load")
    || !fontReadinessFeature.includes("root.classList.remove('ui-font-loading')")
    || !css.includes('html.ui-font-loading body')) {
    failures.push('font readiness: pages must stay hidden until the local Cairo Arabic face is ready');
}
if (!/\.auth-page\s*:is\(button, input, select, textarea\)\s*\{[\s\S]*?font-family:\s*'Cairo',\s*'Cairo Legacy'/.test(css)) {
    failures.push('authentication controls must explicitly use Cairo instead of browser form-control fonts');
}
for (const cairoLocalFontFile of cairoLocalFontFiles) {
    if (!css.includes(`url('/fonts/${cairoLocalFontFile}')`)
        || !fs.existsSync(`public/fonts/${cairoLocalFontFile}`)) {
        failures.push(`application CSS must load the local Cairo face ${cairoLocalFontFile}`);
    }
}
for (const authenticationViewFile of authenticationViewFiles) {
    const authenticationView = fs.readFileSync(authenticationViewFile, 'utf8');
    const inheritsAuthenticationLayout = authenticationView.includes("@extends('layouts.auth')");
    const isCairoEnabledStandaloneScreen = authenticationView.includes('<body class="auth-page auth-page-center">')
        && authenticationView.includes('class="dark ui-font-loading"')
        && authenticationView.includes("@vite(['resources/css/app.css', 'resources/js/app.js'])")
        && authenticationView.includes(`asset('${cairoArabicFontPath}')`);
    if (!inheritsAuthenticationLayout && !isCairoEnabledStandaloneScreen) {
        failures.push(`${authenticationViewFile}: authentication screens must inherit the shared layout or load the same Cairo-enabled entry explicitly`);
    }
    if (authenticationView.includes('<script')
        || authenticationView.includes('<style')
        || /\sstyle=|\son(click|change|submit|input)=/i.test(authenticationView)) {
        failures.push(`${authenticationViewFile}: authentication views must not contain inline CSS or JavaScript`);
    }
}
for (const [authenticationViewFile, fields] of Object.entries(authenticationFieldLabels)) {
    const authenticationView = fs.readFileSync(authenticationViewFile, 'utf8');
    for (const [fieldId, fieldName] of fields) {
        if (!authenticationView.includes(`for="${fieldId}"`)
            || !authenticationView.includes(`id="${fieldId}"`)
            || !authenticationView.includes(`name="${fieldName}"`)) {
            failures.push(`${authenticationViewFile}: ${fieldName} must have a stable id linked to its visible label`);
        }
    }
}
const welcomeScreenView = fs.readFileSync('resources/views/auth/welcome-screen.blade.php', 'utf8');
const suspendedAuthenticationView = fs.readFileSync('resources/views/auth/suspended.blade.php', 'utf8');
if (!suspendedAuthenticationView.includes("auth('web')->check()")
    || !suspendedAuthenticationView.includes("route('logout')")
    || !suspendedAuthenticationView.includes("auth('accountant')->check()")
    || !suspendedAuthenticationView.includes("route('accountant.logout')")
    || !suspendedAuthenticationView.includes('@elseif')) {
    failures.push('suspended authentication view must route owner and accountant logout actions through their matching guards');
}
const welcomeProgressFeaturePath = 'resources/js/features/auth/welcome-progress.js';
const welcomeProgressFeature = fs.readFileSync(welcomeProgressFeaturePath, 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/auth/welcome-progress';")
    || !welcomeScreenView.includes('data-welcome-progress')
    || !welcomeProgressFeature.includes('currentProgress += 5')
    || !welcomeProgressFeature.includes('}, 100);')
    || !welcomeProgressFeature.includes('continueForm.submit();')) {
    failures.push(`${welcomeProgressFeaturePath}: welcome progress timing and automatic continuation must remain centralized and unchanged`);
}
for (const passwordContract of [
    ['resources/views/auth/login.blade.php', ['name="email"', 'name="password"', 'name="remember"']],
    ['resources/views/auth/forgot.blade.php', ['name="email"', "route('password.email')"]],
    ['resources/views/auth/reset.blade.php', ['name="token"', 'name="email"', 'name="password"', 'name="password_confirmation"']],
]) {
    const [authenticationViewFile, requiredContracts] = passwordContract;
    const authenticationView = fs.readFileSync(authenticationViewFile, 'utf8');
    requiredContracts.forEach((requiredContract) => {
        if (!authenticationView.includes(requiredContract)) {
            failures.push(`${authenticationViewFile}: authentication contract must preserve ${requiredContract}`);
        }
    });
}
const loginAuthenticationView = fs.readFileSync('resources/views/auth/login.blade.php', 'utf8');
if (!loginAuthenticationView.includes('id="login-remember"')
    || !loginAuthenticationView.includes('class="auth-check-input peer"')
    || loginAuthenticationView.includes('name="remember" class="hidden peer"')
    || !css.includes('.auth-check-input:focus-visible + .auth-check')) {
    failures.push('login remember option must remain keyboard-focusable with a visible centralized focus state');
}

const errorPageComponentPath = 'resources/views/components/error-page.blade.php';
const errorPageComponent = fs.readFileSync(errorPageComponentPath, 'utf8');
const errorViewFiles = [
    'resources/views/errors/403.blade.php',
    'resources/views/errors/404.blade.php',
    'resources/views/errors/419.blade.php',
    'resources/views/errors/500.blade.php',
    'resources/views/errors/no-access.blade.php',
    'resources/views/errors/store-suspended.blade.php',
];
if (!errorPageComponent.includes("@vite(['resources/css/app.css', 'resources/js/app.js'])")
    || !errorPageComponent.includes('grid w-full grid-cols-1 gap-3 sm:flex')
    || !errorPageComponent.includes('aria-hidden="true"')) {
    failures.push(`${errorPageComponentPath}: shared error page must preserve its responsive and accessible shell`);
}
for (const errorViewFile of errorViewFiles) {
    const errorView = fs.readFileSync(errorViewFile, 'utf8');
    if (!errorView.includes('<x-error-page')
        || errorView.includes('<!DOCTYPE html>')
        || /javascript:/i.test(errorView)
        || errorView.includes('<script')
        || errorView.includes('<style')) {
        failures.push(`${errorViewFile}: error views must use the shared component without inline assets or javascript URLs`);
    }
}
const sessionExpiredView = fs.readFileSync('resources/views/errors/419.blade.php', 'utf8');
if (!sessionExpiredView.includes("href=\"{{ url()->current() }}\"")) {
    failures.push('419 error page must keep a safe refresh link to the current URL');
}

const healthOverviewComponentPath = 'resources/views/components/admin/health-overview.blade.php';
const healthOverviewComponent = fs.readFileSync(healthOverviewComponentPath, 'utf8');
const healthCheckViewFiles = [
    'resources/views/admin/health/credit.blade.php',
    'resources/views/admin/health/debt.blade.php',
];
if (!healthOverviewComponent.includes('$totalIssues > 0 ?')
    || !healthOverviewComponent.includes('$summary[$issueKey] ?? 0')
    || !healthOverviewComponent.includes('aria-label="ملخص فحوص سلامة البيانات"')
    || !healthOverviewComponent.includes('md:w-auto md:min-w-36')) {
    failures.push(`${healthOverviewComponentPath}: health checks must preserve their shared responsive summary contract`);
}
for (const healthCheckViewFile of healthCheckViewFiles) {
    const healthCheckView = fs.readFileSync(healthCheckViewFile, 'utf8');
    if (!healthCheckView.includes('<x-admin.health-overview')
        || !healthCheckView.includes('min-w-[900px]')
        || healthCheckView.includes("$isDanger ? 'ui-border' : 'ui-border'")
        || healthCheckView.includes('$cardClass')) {
        failures.push(`${healthCheckViewFile}: health check must use the shared overview without restored redundant state`);
    }
}
const creditHealthView = fs.readFileSync('resources/views/admin/health/credit.blade.php', 'utf8');
const debtHealthView = fs.readFileSync('resources/views/admin/health/debt.blade.php', 'utf8');
for (const preservedHealthContract of ['$totalIssues', '$issues', '$summary', "$issue['rows']"]) {
    if (!`${healthOverviewComponent}\n${creditHealthView}`.includes(preservedHealthContract)
        || !`${healthOverviewComponent}\n${debtHealthView}`.includes(preservedHealthContract)) {
        failures.push(`admin health checks must preserve data contract: ${preservedHealthContract}`);
    }
}

const publicLandingViewPath = 'resources/views/welcome.blade.php';
const publicLandingView = fs.readFileSync(publicLandingViewPath, 'utf8');
if (!publicLandingView.includes("auth('accountant')->check()")
    || !publicLandingView.includes("route('accountant.logout')")
    || !publicLandingView.includes("auth('web')->check()")
    || !publicLandingView.includes("route('logout')")
    || !publicLandingView.includes("route('login')")
    || !publicLandingView.includes('md:grid-cols-2 lg:grid-cols-4')) {
    failures.push(`${publicLandingViewPath}: landing account action and services grid must remain responsive and guard-aware`);
}
const flashMessagesFeaturePath = 'resources/js/features/ui-flash-messages.js';
const flashMessagesFeature = fs.readFileSync(flashMessagesFeaturePath, 'utf8');
const dashboardFlashLayout = fs.readFileSync('resources/views/dashboard/app.blade.php', 'utf8');
if (!fs.readFileSync('resources/js/app.js', 'utf8').includes("import './features/ui-flash-messages';")
    || !dashboardFlashLayout.includes('data-ui-flash-messages')
    || !dashboardFlashLayout.includes('data-success-message="{{ session(\'success\') }}"')
    || !dashboardFlashLayout.includes('data-error-message="{{ session(\'error\') }}"')
    || !dashboardFlashLayout.includes('data-warning-message="{{ session(\'warning\') }}"')
    || !dashboardFlashLayout.includes('data-info-message="{{ session(\'info\') ?: session(\'status\') }}"')
    || !flashMessagesFeature.includes('window.Swal.fire')
    || !flashMessagesFeature.includes("icon: 'warning'")
    || !flashMessagesFeature.includes("icon: 'info'")
    || !flashMessagesFeature.includes('timer: 3000')
    || !flashMessagesFeature.includes('timerProgressBar: true')) {
    failures.push(`${flashMessagesFeaturePath}: dashboard flash messages must remain safely centralized with their existing toast behavior`);
}
if (fs.existsSync('resources/views/alerts/alerts.blade.php')) {
    failures.push('unused legacy alerts view with duplicate SweetAlert loading must not return');
}

const fullPaginationPath = 'resources/views/vendor/pagination/tailwind.blade.php';
const simplePaginationPath = 'resources/views/vendor/pagination/simple-tailwind.blade.php';
const fullPagination = fs.readFileSync(fullPaginationPath, 'utf8');
const simplePagination = fs.readFileSync(simplePaginationPath, 'utf8');
if (!fullPagination.includes('aria-label="التنقل بين صفحات النتائج"')
    || !fullPagination.includes('aria-current="page"')
    || !fullPagination.includes('overflow-x-auto')
    || !fullPagination.includes('rel="prev"')
    || !fullPagination.includes('rel="next"')) {
    failures.push(`${fullPaginationPath}: full pagination must remain responsive and accessible`);
}
if (!simplePagination.includes('aria-label="التنقل بين صفحات النتائج"')
    || !simplePagination.includes('aria-disabled="true"')
    || !simplePagination.includes('rel="prev"')
    || !simplePagination.includes('rel="next"')) {
    failures.push(`${simplePaginationPath}: simple pagination must remain responsive and accessible`);
}
const fullPaginationAliases = ['bootstrap-4', 'bootstrap-5', 'default', 'semantic-ui'];
const simplePaginationAliases = ['simple-bootstrap-4', 'simple-bootstrap-5', 'simple-default'];
for (const alias of fullPaginationAliases) {
    const aliasPath = `resources/views/vendor/pagination/${alias}.blade.php`;
    if (fs.readFileSync(aliasPath, 'utf8').trim() !== "@include('pagination::tailwind')") {
        failures.push(`${aliasPath}: full pagination aliases must use the shared template`);
    }
}
for (const alias of simplePaginationAliases) {
    const aliasPath = `resources/views/vendor/pagination/${alias}.blade.php`;
    if (fs.readFileSync(aliasPath, 'utf8').trim() !== "@include('pagination::simple-tailwind')") {
        failures.push(`${aliasPath}: simple pagination aliases must use the shared template`);
    }
}

const resetEmailPath = 'resources/views/emails/reset.blade.php';
const responsiveResetEmail = fs.readFileSync(resetEmailPath, 'utf8');
if (!responsiveResetEmail.includes('max-width:600px')
    || !responsiveResetEmail.includes("font-family:'Cairo','DejaVu Sans'")
    || !responsiveResetEmail.includes('overflow-wrap:anywhere')
    || !responsiveResetEmail.includes('href="{{ $resetUrl }}"')
    || !responsiveResetEmail.includes('{{ $resetUrl }}')) {
    failures.push(`${resetEmailPath}: password reset email must remain responsive while preserving its reset URL contract`);
}

const adminUserViewFiles = [
    'resources/views/admin/users/index.blade.php',
    'resources/views/admin/users/edit.blade.php',
    'resources/views/admin/users/show.blade.php',
    'resources/views/admin/users/trash.blade.php',
];
for (const adminUserViewFile of adminUserViewFiles) {
    const adminUserView = fs.readFileSync(adminUserViewFile, 'utf8');
    if (adminUserView.includes('onsubmit=')
        || adminUserView.includes('onclick=')
        || adminUserView.includes('<script')
        || adminUserView.includes('<style')
        || /\sstyle="/i.test(adminUserView)) {
        failures.push(`${adminUserViewFile}: user management must not restore inline scripts, styles, or browser confirmations`);
    }
}
const adminUsersIndex = fs.readFileSync('resources/views/admin/users/index.blade.php', 'utf8');
const adminUserController = fs.readFileSync('app/Http/Controllers/Admin/UserController.php', 'utf8');
if (!adminUsersIndex.includes('class="ui-modal-backdrop p-4"')
    || !adminUsersIndex.includes('class="ui-modal-panel')
    || !adminUsersIndex.includes('sm:grid-cols-2')
    || !adminUsersIndex.includes('editUser = @js([')
    || adminUsersIndex.includes('openRow:')
    || adminUsersIndex.includes("session('success')")) {
    failures.push('admin users index must keep centered responsive modals, safe edit payloads, and centralized flash messages');
}
if (!adminUsersIndex.includes('name="allowed_stores" x-model="editUser.allowed_stores"')
    || !adminUsersIndex.includes('name="allowed_accountants" x-model="editUser.allowed_accountants"')
    || adminUsersIndex.includes('App\\Models\\Plan::all()')
    || adminUsersIndex.includes('App\\Models\\User::onlyTrashed()')
    || !adminUserController.includes("compact('users', 'plans', 'trashCount')")) {
    failures.push('admin users quick edit must submit required limits and receive list data from its controller');
}
for (const userField of ['name', 'phone', 'email', 'plan_id', 'password', 'password_confirmation', 'status', 'subscription_end_at', 'expires_at']) {
    if (!adminUsersIndex.includes(`name="${userField}"`)) {
        failures.push(`admin users index must preserve form field: ${userField}`);
    }
}
const adminUserShow = fs.readFileSync('resources/views/admin/users/show.blade.php', 'utf8');
const adminUserTrash = fs.readFileSync('resources/views/admin/users/trash.blade.php', 'utf8');
if (!adminUserShow.includes('data-ui-confirm-title="تأكيد تغيير حالة الحساب"')
    || !adminUserShow.includes('data-ui-confirm-title="نقل المستخدم إلى السلة؟"')
    || !adminUserShow.includes('نقل الحساب إلى السلة')
    || adminUserShow.includes('حذف الحساب نهائياً')) {
    failures.push('admin user details must describe soft deletion accurately and use shared confirmations');
}
if (!adminUserTrash.includes('data-ui-confirm-title="استعادة المستخدم؟"')
    || !adminUserTrash.includes('data-ui-confirm-title="حذف المستخدم نهائيًا؟"')
    || !adminUserTrash.includes('ui-table min-w-[560px]')) {
    failures.push('admin user trash must keep responsive table and shared restore/delete confirmations');
}

const sharedHelpComponentPath = 'resources/views/components/ui/help.blade.php';
const sharedHelpModalPath = 'resources/views/components/ui/help-modal.blade.php';
const sharedHelpScriptPath = 'resources/js/features/ui-help-modal.js';
const sharedButtonComponentPath = 'resources/views/components/ui/button.blade.php';
const sharedHelpComponent = fs.readFileSync(sharedHelpComponentPath, 'utf8');
const sharedHelpModal = fs.readFileSync(sharedHelpModalPath, 'utf8');
const sharedHelpScript = fs.readFileSync(sharedHelpScriptPath, 'utf8');
const sharedButtonComponent = fs.readFileSync(sharedButtonComponentPath, 'utf8');
if (!sharedHelpComponent.includes('data-ui-help-title')
    || !sharedHelpComponent.includes('data-ui-help-body')) {
    failures.push(`${sharedHelpComponentPath}: shared help trigger must target the universal help dialog`);
}
if (!sharedHelpModal.includes('data-ui-help-modal')
    || !sharedHelpModal.includes('role="dialog"')
    || !sharedHelpModal.includes('aria-modal="true"')
    || !sharedHelpModal.includes('ui-help-modal-panel')
    || !sharedHelpModal.includes('ui-help-modal-header')
    || !sharedHelpModal.includes('ui-help-modal-close')
    || !sharedHelpModal.includes('ui-help-modal-body')) {
    failures.push(`${sharedHelpModalPath}: universal help dialog must remain centered, dismissible, and accessible`);
}
if (!sharedHelpScript.includes("event.key === 'Escape'")
    || !sharedHelpScript.includes("event.target === modal()")
    || !sharedHelpScript.includes("closest('[data-ui-help-title]')")
    || !sharedHelpScript.includes("trigger.closest('.ui-modal-panel')")
    || !sharedHelpScript.includes('window.setTimeout(() => restoreHelpModalPosition')) {
    failures.push(`${sharedHelpScriptPath}: universal help dialog must support triggers, backdrop dismissal, and Escape`);
}
for (const helpDialogViewPath of [
    'resources/views/accountants/pos/expense.blade.php',
    'resources/views/cashier/quick-sale/index.blade.php',
    'resources/views/dashboard/accountant/index.blade.php',
    'resources/views/user/stores/daily.blade.php',
    'resources/views/user/stores/reports/monthly.blade.php',
    'resources/views/user/stores/products/create.blade.php',
    'resources/views/user/stores/products/edit.blade.php',
]) {
    const helpDialogView = fs.readFileSync(helpDialogViewPath, 'utf8');
    const usesHelpComponent = helpDialogView.includes('<x-ui.help');
    const usesHelpDataContract = helpDialogView.includes('data-ui-help-title')
        && helpDialogView.includes('data-ui-help-body');
    if (!usesHelpComponent && !usesHelpDataContract) {
        failures.push(`${helpDialogViewPath}: help triggers must use the universal help dialog contract`);
    }
}
if (!sharedButtonComponent.includes("'warning' => 'ui-btn-warning'")
    || !sharedButtonComponent.includes("'secondary' => 'ui-btn-secondary'")
    || !sharedButtonComponent.includes("'danger' => 'ui-btn-danger'")) {
    failures.push(`${sharedButtonComponentPath}: shared button must preserve all approved visual roles`);
}

const adminRoutesPath = 'routes/admin.php';
const adminRoutes = fs.readFileSync(adminRoutesPath, 'utf8');
const adminUsersResourceCount = (adminRoutes.match(/Route::resource\('users'/g) || []).length;
if (adminUsersResourceCount !== 1
    || !adminRoutes.includes("Route::delete('{id}/force-delete', [AdminUserController::class, 'forceDelete'])")
    || !adminRoutes.includes("Route::resource('users', AdminUserController::class)->except(['create'])")
    || adminRoutes.includes("[UserController::class, 'quickSearch']")
    || adminRoutes.includes("[UserController::class, 'renewSubscription']")
    || adminRoutes.includes("[UserController::class, 'export']")
    || adminRoutes.includes("[AdminUserController::class, 'suspend']")
    || adminRoutes.includes("[AdminUserController::class, 'activate']")) {
    failures.push(`${adminRoutesPath}: admin user routes must remain unique, protected, and mapped to existing controller actions`);
}
const adminNavbar = fs.readFileSync('resources/views/dashboard/navbars/admin.blade.php', 'utf8');
const adminSidebarPath = 'resources/views/dashboard/sidebars/admin.blade.php';
const adminSidebar = fs.readFileSync(adminSidebarPath, 'utf8');
const dashboardFooterPath = 'resources/views/dashboard/footer.blade.php';
const dashboardFooter = fs.readFileSync(dashboardFooterPath, 'utf8');
if (!adminNavbar.includes("route('admin.users.index', ['add' => 1])")
    || !adminSidebar.includes("route('admin.users.index', ['add' => 1])")
    || adminNavbar.includes("route('admin.users.create')")
    || adminSidebar.includes("route('admin.users.create')")) {
    failures.push('admin add-user links must open the existing responsive modal instead of a missing create route');
}
if (fs.existsSync('resources/views/dashboard/sidebars/user.blade.php')) {
    failures.push('unused owner sidebar with Blade database queries must not return');
}
if (adminSidebar.includes('href="#"')
    || !adminSidebar.includes(':aria-expanded="open"')
    || !adminSidebar.includes('aria-controls="admin-users-menu"')) {
    failures.push(`${adminSidebarPath}: admin sidebar must remain free of placeholder links and expose its collapsed states accessibly`);
}
for (const preservedNotificationRoute of ['admin.notifications.index', 'notifications.internal.send', 'admin.onesignal.index', 'admin.notifications.push']) {
    if (!adminSidebar.includes(`route('${preservedNotificationRoute}')`)) {
        failures.push(`${adminSidebarPath}: unrelated notification navigation must remain unchanged: ${preservedNotificationRoute}`);
    }
}
if (dashboardFooter.includes('href="#"')
    || dashboardFooter.includes('الإصدار 1.0.0')
    || !dashboardFooter.includes("{{ date('Y') }} جميع الحقوق محفوظة")) {
    failures.push(`${dashboardFooterPath}: footer must not restore placeholder links or a hard-coded version`);
}

const dashboardLayoutPath = 'resources/views/dashboard/app.blade.php';
const dashboardLayout = fs.readFileSync(dashboardLayoutPath, 'utf8');
if (!dashboardLayout.includes(`asset('${cairoArabicFontPath}')`)
    || !dashboardLayout.includes('class="dark ui-font-loading"')
    || !css.includes(`url('/${cairoArabicFontPath}')`)
    || !fs.existsSync(`public/${cairoArabicFontPath}`)) {
    failures.push(`${dashboardLayoutPath}: dashboard and application CSS must load the same local Cairo Arabic WOFF2 face as authentication`);
}
if (/fonts\.googleapis\.com|fonts\.gstatic\.com/.test(authenticationLayout + dashboardLayout)) {
    failures.push('authentication and dashboard layouts must rely on the shared local Cairo faces, not Google Fonts');
}
const dashboardThemePath = 'public/js/dashboard-theme.js';
const dashboardTheme = fs.readFileSync(dashboardThemePath, 'utf8');
const dashboardNavigationFeature = fs.readFileSync('resources/js/features/dashboard-navigation.js', 'utf8');
const ownerNavbar = fs.readFileSync('resources/views/dashboard/navbars/user.blade.php', 'utf8');
const accountantNavbar = fs.readFileSync('resources/views/dashboard/navbars/accountant.blade.php', 'utf8');
if (fs.existsSync('resources/views/dashboard/header.blade.php')) {
    failures.push('unused legacy dashboard header with duplicate Alpine and demo content must not return');
}
if (!dashboardLayout.includes("asset('js/dashboard-theme.js')")
    || /<script>\s*const savedTheme/.test(dashboardLayout)
    || !dashboardTheme.includes("localStorage.getItem('theme')")
    || !dashboardTheme.includes("classList.toggle('light', useLightTheme)")
    || !dashboardTheme.includes("classList.toggle('dark', !useLightTheme)")) {
    failures.push(`${dashboardLayoutPath}: theme initialization must remain external and run before the dashboard is painted`);
}
if (!ownerNavbar.includes('data-theme-toggle')
    || !accountantNavbar.includes('data-theme-toggle')
    || [ownerNavbar, accountantNavbar].some((navbar) => !navbar.includes('data-theme-icon="light"') || !navbar.includes('data-theme-icon="dark"'))
    || ownerNavbar.includes('toggleTheme()')
    || accountantNavbar.includes('localStorage.setItem')
    || !dashboardNavigationFeature.includes("document.querySelectorAll('[data-theme-toggle]')")
    || !dashboardNavigationFeature.includes("localStorage.setItem('theme', useDarkTheme ? 'dark' : 'light')")) {
    failures.push('owner and accountant theme controls must use the shared dashboard navigation feature');
}
// تحمي العقود موضع النوافذ والتولتيب، وأيقونة ثيم واحدة، وفتح بطاقة متجر واحدة فقط.
if (!/\[data-theme-icon\]\s*\{\s*display:\s*none\s*!important;/.test(css)
    || !/html\.light \[data-theme-icon="dark"\],[\s\S]*?html\.dark \[data-theme-icon="light"\]\s*\{\s*display:\s*inline-flex\s*!important;/.test(css)) {
    failures.push('theme controls must display exactly one opposite-theme icon in light and dark modes');
}
if (!css.includes('.ui-tooltip-popover {')
    || !/\.ui-tooltip-popover\s*\{[\s\S]*?position:\s*fixed;[\s\S]*?top:\s*50%;[\s\S]*?left:\s*50%;/.test(css)) {
    failures.push('shared tooltips must remain centered in the viewport on every screen size');
}
if (allViewBladeFiles.some((bladeFilePath) => /ui-modal-backdrop[^"\n]*items-start/.test(fs.readFileSync(bladeFilePath, 'utf8')))) {
    failures.push('shared modal backdrops must not override exact viewport centering');
}
if (!ownerNavbar.includes('data-store-accordion')
    || !ownerNavbar.includes('data-store-accordion-item')
    || !dashboardNavigationFeature.includes("document.querySelectorAll('[data-store-accordion]')")
    || !dashboardNavigationFeature.includes('otherItem.open = false')) {
    failures.push('owner store dropdown must keep all store names while opening only one store card');
}
const storeShowView = fs.readFileSync('resources/views/user/stores/show.blade.php', 'utf8');
if (!['ui-dot-danger', 'ui-dot-warning', 'ui-dot-success'].every((dotClass) => storeShowView.includes(dotClass))
    || /w-2 h-2 rounded-full ui-status-(?:danger|warning|success)-bg/.test(storeShowView)) {
    failures.push('store inventory audit traffic-light dots must use strong status dots in light mode');
}
if (!/\.ui-dot-success\s*\{[\s\S]*?background-color:\s*var\(--ui-dot-success\)\s*!important;/.test(css)
    || !/\.ui-dot-warning\s*\{[\s\S]*?background-color:\s*var\(--ui-dot-warning\)\s*!important;/.test(css)
    || !/\.ui-dot-danger\s*\{[\s\S]*?background-color:\s*var\(--ui-dot-danger\)\s*!important;/.test(css)) {
    failures.push('central inventory dots must keep explicit green, yellow, and red colors in both themes');
}
if (!/\.ui-modal-header\s*>\s*\.ui-modal-close-danger\s*\{[\s\S]*?position:\s*absolute;[\s\S]*?left:\s*1rem;/.test(css)) {
    failures.push('modal and help close buttons must remain aligned on the far left in every language');
}
const statCardPath = 'resources/views/components/stat-card.blade.php';
const statCard = fs.readFileSync(statCardPath, 'utf8');
if (!statCard.includes("'valueId' => null")
    || !statCard.includes('id="{{ $valueId }}"')
    || !statCard.includes("$attributes->class(['ui-card rounded-2xl p-5'])")) {
    failures.push(`${statCardPath}: stat cards must expose their requested live-value ID and preserve caller attributes`);
}

const applicationScript = fs.readFileSync('resources/js/app.js', 'utf8');
if (/cdn[^"']*alpinejs|alpinejs[^"']*cdn/i.test(dashboardLayout)) {
    failures.push('dashboard layout must not load a second Alpine instance from a CDN');
}
if (!applicationScript.includes("import Alpine from 'alpinejs';") || !applicationScript.includes('Alpine.start();')) {
    failures.push('application script must remain the single Alpine entry point');
}

const dashboardNavigationFiles = [
    'resources/views/dashboard/navbars/user.blade.php',
    'resources/views/dashboard/navbars/admin.blade.php',
    'resources/views/dashboard/navbars/accountant.blade.php',
];
for (const dashboardNavigationFile of dashboardNavigationFiles) {
    const dashboardNavigation = fs.readFileSync(dashboardNavigationFile, 'utf8');
    if (dashboardNavigation.includes('@php') || dashboardNavigation.includes('<script')) {
        failures.push(`${dashboardNavigationFile}: navigation data and scripts must remain outside Blade`);
    }
    if (!dashboardNavigation.includes('data-realtime-notifications')) {
        failures.push(`${dashboardNavigationFile}: realtime notifications must use the shared feature module`);
    }
}

const dashboardNotificationsScript = fs.readFileSync('resources/js/features/dashboard-notifications.js', 'utf8');
if (dashboardNotificationsScript.includes('innerHTML')) {
    failures.push('dashboard notifications must render untrusted content with textContent');
}

if (failures.length) {
    console.error('Visual identity contract failed:');
    failures.forEach((failure) => console.error(`- ${failure}`));
    process.exit(1);
}

console.log('Visual identity contract passed.');
