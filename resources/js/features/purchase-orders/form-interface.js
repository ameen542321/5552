// ينقل منشئ طلب الشراء كما هو؛ المنتجات والمسودة وحالة التعديل تصل عبر عقد إعداد آمن.
const root = document.querySelector('[data-purchase-order-form-config]');
if (root) {
    const config = JSON.parse(root.dataset.purchaseOrderFormConfig || '{}');
    function calculateCartonText(qty, cartonQty, isSplittable) {
        if (!cartonQty || cartonQty <= 0 || qty < cartonQty) return '';
        const cartons = Math.floor(qty / cartonQty);
        const pieces = qty % cartonQty;
        const unitName = isSplittable ? 'طقم' : 'حبة';
        let text = `${cartons} كرتون`;
        if (pieces > 0) text += ` و ${pieces} ${unitName}`;
        return text;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const products = config.products || [];
        const existingProductRows = config.existingProductRows || [];
        const existingCustomRows = config.existingCustomRows || [];
        const isEdit = Boolean(config.isEdit);
        const hasServerErrors = Boolean(config.hasServerErrors);
        const serverError = String(config.serverError || '').trim();
        const hideInventoryValues = Boolean(config.hideInventoryValues);
        const skipConfirmation = Boolean(config.skipConfirmation);
        // المسودة معزولة بحسب المالك والمتجر، وتنتهي بعد سبعة أيام من آخر تعديل.
        const draftKey = config.draftKey;
        const draftLifetime = 7 * 24 * 60 * 60 * 1000;

        function hasDraftContent(draft) {
            return Boolean(
                String(draft?.supplier_name || '').trim()
                || String(draft?.notes || '').trim()
                || (Array.isArray(draft?.product_rows) && draft.product_rows.length > 0)
                || (Array.isArray(draft?.custom_rows) && draft.custom_rows.length > 0)
            );
        }

        const input = document.getElementById('productPickerInput');
        const menu = document.getElementById('productPickerMenu');
        const list = document.getElementById('orderItemsList');
        const orderRowsSearch = document.getElementById('orderRowsSearch');
        const orderRowsSearchCount = document.getElementById('orderRowsSearchCount');

        let rowIndex = 0;
        let customIndex = 0;
        let restoringDraft = false;
        let draftSaveTimer = null;
        let submissionInProgress = false;

        const money = new Intl.NumberFormat('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (serverError && typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'تعذر حفظ الطلبية',
                text: serverError,
                icon: 'warning',
                confirmButtonText: 'حسنًا',
                background: '',
                color: '',
                confirmButtonColor: '',
            });
        }

        function draftSnapshot(pendingSubmission = false) {
            const productRows = [];
            const customRows = [];

            document.querySelectorAll('.js-item-row').forEach((row) => {
                if (row.dataset.isCustom === 'true') {
                    customRows.push({
                        custom_product_name: row.querySelector('.js-input-name')?.value || '',
                        quantity_requested: row.querySelector('.js-input-qty')?.value || '',
                        cost_price_at_order: row.querySelector('input[name*="[cost_price_at_order]"]')?.value || '',
                        unit_type: row.querySelector('select[name*="[unit_type]"]')?.value || 'unit',
                        items_per_unit: row.querySelector('input[name*="[items_per_unit]"]')?.value || '',
                        roll_length: row.querySelector('input[name*="[roll_length]"]')?.value || '',
                        receipt_notes: row.querySelector('.js-input-notes')?.value || '',
                        add_to_owner_purchases: Boolean(row.querySelector('input[name*="[add_to_owner_purchases]"]')?.checked),
                    });
                    return;
                }

                productRows.push({
                    product_id: Number(row.dataset.productId),
                    quantity_requested: row.querySelector('.js-input-qty')?.value || '',
                    unit_type: row.querySelector('.js-input-unit')?.value || 'unit',
                    receipt_notes: row.querySelector('.js-input-notes')?.value || '',
                });
            });

            return {
                saved_at: Date.now(),
                pending_submission: pendingSubmission,
                supplier_name: document.querySelector('[name="supplier_name"]')?.value || '',
                notes: document.querySelector('[name="notes"]')?.value || '',
                product_rows: productRows,
                custom_rows: customRows,
            };
        }

        function saveDraft(pendingSubmission = false) {
            if (isEdit || restoringDraft) return;
            try {
                localStorage.setItem(draftKey, JSON.stringify(draftSnapshot(pendingSubmission)));
            } catch (error) {
                // تعذر التخزين لا يمنع المستخدم من إكمال الطلبية أو إرسالها.
            }
        }

        function scheduleDraftSave() {
            if (isEdit || restoringDraft) return;
            window.clearTimeout(draftSaveTimer);
            draftSaveTimer = window.setTimeout(() => saveDraft(false), 250);
        }

        function readDraft() {
            try {
                const draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
                if (!draft || !draft.saved_at || Date.now() - Number(draft.saved_at) > draftLifetime || !hasDraftContent(draft)) {
                    localStorage.removeItem(draftKey);
                    return null;
                }
                // الإرسال الناجح يغادر صفحة الإنشاء؛ عند فتح طلبية جديدة نحذف المسودة المعلّمة كمرسلة.
                if (draft.pending_submission && !hasServerErrors) {
                    localStorage.removeItem(draftKey);
                    return null;
                }
                return draft;
            } catch (error) {
                localStorage.removeItem(draftKey);
                return null;
            }
        }

        function updateRowNumbers() {
            const rows = Array.from(document.querySelectorAll('.js-item-row'));
            rows.forEach((row, index) => {
                const sequenceNumber = index + 1;
                row.querySelectorAll('.js-row-num').forEach(el => el.textContent = sequenceNumber);
            });
            updateRowsSearch();
            scheduleDraftSave();
        }

        function getIncompleteCustomRows() {
            return Array.from(document.querySelectorAll('.js-item-row[data-is-custom="true"]')).filter((row) => {
                const nameField = row.querySelector('.js-input-name');
                return !String(nameField?.value || '').trim();
            });
        }

        function preventAddingUntilCustomRowIsFilled() {
            const incompleteCustomRows = getIncompleteCustomRows();
            if (!incompleteCustomRows.length) {
                return false;
            }
            const firstIncomplete = incompleteCustomRows[0];
            openRow(firstIncomplete);
            firstIncomplete.querySelector('.js-input-name')?.focus();

            if (typeof Swal !== 'undefined') {
                Swal.fire('تنبيه', 'أكمل اسم المنتج في السطر المخصص الحالي أولاً قبل إضافة سطر جديد.', 'warning');
            } else {
                alert('أكمل اسم المنتج في السطر المخصص الحالي أولاً قبل إضافة سطر جديد.');
            }
            return true;
        }

        function removeIncompleteCustomRows() {
            const incompleteCustomRows = getIncompleteCustomRows();
            incompleteCustomRows.forEach((row) => row.remove());
            updateRowNumbers();
            return incompleteCustomRows.length > 0;
        }

        function closeAllRows() {
            document.querySelectorAll('.js-item-row').forEach(row => {
                const isCustom = row.dataset.isCustom === 'true';
                const expanded = row.querySelector('.js-expanded-view');
                const collapsed = row.querySelector('.js-collapsed-view');

                if (!expanded.classList.contains('hidden')) {
                    let name = isCustom ? (row.querySelector('.js-input-name')?.value || 'منتج جديد') : row.querySelector('.js-lbl-name').textContent;
                    const qtyInput = row.querySelector('.js-input-qty');
                    const notesInput = row.querySelector('.js-input-notes');

                    const qty = qtyInput ? qtyInput.value : '';
                    const notes = notesInput ? notesInput.value : '';

                    collapsed.querySelector('.js-col-name').textContent =  name;
                    collapsed.querySelector('.js-col-qty').textContent = 'الكمية : ' + (qty || '0');

                    const colNotes = collapsed.querySelector('.js-col-notes');
                    if (colNotes) {
                        if (notes.trim() !== '') {
                            colNotes.textContent = 'الملاحظات: ' + notes;
                            colNotes.classList.remove('hidden');
                        } else {
                            colNotes.classList.add('hidden');
                        }
                    }

                    expanded.classList.add('hidden');
                    collapsed.classList.remove('hidden');
                }
            });
        }

        function openRow(row) {
            closeAllRows();
            row.querySelector('.js-collapsed-view').classList.add('hidden');
            row.querySelector('.js-expanded-view').classList.remove('hidden');
        }

        function normalizeArabic(text) {
            if (!text) return '';
            return String(text).trim().toLowerCase()
                .replace(/[أإآ]/g, 'ا')
                .replace(/ة/g, 'ه')
                .replace(/ى/g, 'ي')
                .replace(/[\u064B-\u065F]/g, '');
        }


        function rowSearchText(row) {
            return normalizeArabic([
                row.querySelector('.js-lbl-name')?.textContent,
                row.querySelector('.js-col-name')?.textContent,
                row.querySelector('.js-input-name')?.value,
                row.querySelector('.js-input-notes')?.value,
                row.querySelector('.js-col-notes')?.textContent,
            ].filter(Boolean).join(' '));
        }

        function updateRowsSearch() {
            if (!orderRowsSearch) return;
            const normalizedTerm = normalizeArabic(orderRowsSearch.value || '');
            const termWithoutAlef = normalizedTerm.replace(/ا/g, '');
            const rows = Array.from(document.querySelectorAll('.js-item-row'));
            let visibleCount = 0;

            rows.forEach((row) => {
                const text = rowSearchText(row);
                const textWithoutAlef = text.replace(/ا/g, '');
                const isMatch = !normalizedTerm || text.includes(normalizedTerm) || textWithoutAlef.includes(termWithoutAlef);
                row.classList.toggle('hidden', !isMatch);
                if (isMatch) visibleCount += 1;
            });

            if (orderRowsSearchCount) {
                orderRowsSearchCount.textContent = rows.length
                    ? `المعروض ${visibleCount} من ${rows.length} بند.`
                    : 'لم تتم إضافة أي بند بعد.';
            }
        }

        function renderMenu(term = '') {
            const normalizedTerm = normalizeArabic(term);
            const termWithoutAlef = normalizedTerm.replace(/ا/g, '');

            const matches = products
                .filter((product) => {
                    if (!normalizedTerm) return true;
                    const name = normalizeArabic(product.name || '');
                    const desc = normalizeArabic(product.description || '');
                    if (name.includes(normalizedTerm) || desc.includes(normalizedTerm)) return true;
                    const nameWithoutAlef = name.replace(/ا/g, '');
                    const descWithoutAlef = desc.replace(/ا/g, '');
                    if (nameWithoutAlef.includes(termWithoutAlef) || descWithoutAlef.includes(termWithoutAlef)) return true;
                    return false;
                })
                .slice(0, 30);

            if (!matches.length) {
                menu.innerHTML = '<div class="p-4 text-sm ui-text-muted">لا توجد نتائج مطابقة، يمكنك استخدام زر المنتج المخصص.</div>';
                menu.classList.remove('hidden');
                return;
            }

            menu.innerHTML = matches.map((product) => {
                const cost = parseFloat(product.cost_price) || 0;
                const price = parseFloat(product.price) || 0;
                const isOwnerPurchase = Boolean(product.is_owner_purchase);
                const isInvalid = !hideInventoryValues && !isOwnerPurchase && (cost <= 0 || price <= 0);
                const isSelected = document.querySelector(`.js-item-row[data-product-id="${product.id}"]`);

                let classes = 'w-full text-right p-3 border-b ui-border last:ui-border flex justify-between items-center transition ';
                let badge = '';
                let clickClass = '';

                if (isSelected) {
                    classes += 'opacity-50 cursor-not-allowed ui-surface-muted-bg';
                    badge = '<span class="ui-text-caption ui-status-info-bg ui-status-info px-2 py-0.5 rounded border ui-border">مضاف مسبقاً</span>';
                } else if (isInvalid) {
                    classes += 'opacity-50 cursor-not-allowed ui-status-danger-bg';
                    badge = '<span class="ui-text-caption ui-status-danger-bg ui-status-danger px-2 py-0.5 rounded border ui-border">بحاجة للمراجعة</span>';
                } else {
                    classes += 'ui-surface-muted-bg cursor-pointer js-pick-product';
                    clickClass = 'js-pick-product';
                    if (isOwnerPurchase) {
                        badge = '<span class="ui-text-caption ui-status-warning-bg ui-status-warning px-2 py-0.5 rounded border ui-border">مشتريات مالك</span>';
                    }
                }

                const auditColor = product.audit_color || 'red';
                const auditLabel = product.audit_label || 'غير مكتمل';
                const auditDot = hideInventoryValues ? '' : `<span class="inline-flex w-2 h-2 rounded-full ${product.audit_dot_class || 'ui-surface-muted-bg'} flex-shrink-0" title="${auditLabel}"></span>`;

                return `
                    <button type="button" data-product-id="${product.id}" class="${classes} ${clickClass}">
                        <div class="flex items-center gap-2 text-right">
                            ${auditDot}
                            <div>
                                <span class="block ui-title font-bold text-sm">${product.name}</span>
                                ${hideInventoryValues ? '' : `<span class="block ui-text-caption ui-text-muted mt-1">${isOwnerPurchase ? 'مشتريات مالك / استهلاك' : `الكمية: ${money.format(product.quantity)} • التكلفة: ${money.format(cost)}`}</span>`}
                            </div>
                        </div>
                        <div>${badge}</div>
                    </button>
                `;
            }).join('');
            menu.classList.remove('hidden');
        }

        function addProductRow(product, initial = {}) {
            const idx = rowIndex++;
            const hasUnitOptions = product.unit_options && product.unit_options.length > 0;
            const isOwnerPurchase = Boolean(product.is_owner_purchase);

            const unitField = hasUnitOptions
                ? `<select name="items[${idx}][unit_type]" class="js-input-unit w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">
                    ${product.unit_options.map((option) => `<option value="${option.value}" ${option.value === product.default_unit ? 'selected' : ''}>${option.label}</option>`).join('')}
                   </select>`
                : `<input type="hidden" name="items[${idx}][unit_type]" value="unit" class="js-input-unit">`;

            const wrapper = document.createElement('div');
            wrapper.dataset.productId = product.id;
            wrapper.className = 'js-item-row ui-card overflow-hidden mb-2';

            wrapper.innerHTML = `
                <div class="js-collapsed-view cursor-pointer p-3 text-sm ui-text-muted ui-surface-muted-bg transition flex justify-between items-center hidden">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="js-row-num ui-text-caption font-black ui-text-muted px-2 py-0.5 rounded ui-surface-muted-bg border ui-border"></span>
                        <span class="js-col-name font-bold ui-title"></span>
                        <span class="ui-text-muted">|</span>
                        <span class="js-col-qty ui-status-success font-bold"></span>
                        <span class="js-col-notes ui-text-caption ui-status-warning hidden px-2 ui-status-warning-bg rounded border ui-border"></span>
                    </div>
                    <span class="shrink-0 rounded-full border ui-border ui-surface-muted-bg px-2 py-0.5 ui-text-caption ui-text-muted" aria-hidden="true">▼</span>
                </div>

                <div class="js-expanded-view p-4 space-y-3 relative ui-border ui-surface-muted-bg">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="js-row-num ui-text-caption font-black ui-text-muted px-1.5 py-0.5 rounded ui-surface-muted-bg border ui-border"></span>
                                ${hideInventoryValues ? '' : `<span class="inline-flex w-2 h-2 rounded-full ${product.audit_dot_class || 'ui-surface-muted-bg'} flex-shrink-0" title="${product.audit_label || 'غير مكتمل'}"></span>`}
                                <span class="js-lbl-name font-black ui-title text-base">${product.name}</span>
                                ${isOwnerPurchase ? '<span class="inline-flex items-center ui-text-caption font-bold ui-status-warning">مشتريات مالك</span>' : ''}
                            </div>
                            ${hideInventoryValues ? '' : `<div class="ui-text-caption ui-text-muted">الكمية الموجودة: ${money.format(product.quantity)} • التكلفة: ${money.format(product.cost_price)}</div>`}
                        </div>
                        <button type="button" title="إغلاق السطر" aria-label="إغلاق السطر" class="js-collapse-row shrink-0 rounded-full border ui-border ui-surface-muted-bg px-2.5 py-1 ui-text-caption font-bold ui-text-muted transition">▲</button>
                    </div>

                    <input type="hidden" name="items[${idx}][product_id]" value="${product.id}">
                    ${isOwnerPurchase ? `
                        <div class="ui-status-warning-bg px-3 py-2 ui-text-caption ui-status-warning flex items-center gap-2 rounded-lg">
                            <input type="checkbox" checked disabled class="rounded ui-border ui-surface-muted-bg ui-status-warning">
                            <span class="font-bold">مشتريات مالك</span>
                        </div>
                    ` : ''}

                    <div class="grid grid-cols-1 md:grid-cols-[150px_1fr] gap-3">
                        <div class="flex flex-col gap-1">
                            <input name="items[${idx}][quantity_requested]" type="number" step="0.01" min="0" placeholder="الكمية المطلوبة" class="js-input-qty w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">
                            <span class="js-carton-text ui-text-caption ui-status-success font-bold block mt-1"></span>
                            ${hasUnitOptions ? unitField : ''}
                        </div>
                        <div class="flex flex-col gap-1">
                            <input name="items[${idx}][receipt_notes]" maxlength="255" placeholder="ملاحظات إضافية إن وجدت (لون، مقاس...)" class="js-input-notes w-full h-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">
                        </div>
                    </div>

                    <div class="pt-1">
                        <button type="button" class="js-remove ui-btn ui-btn-danger px-3 py-2 ui-text-caption">حذف السطر</button>
                    </div>
                </div>
            `;

            wrapper.querySelector('.js-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                wrapper.remove();
                updateRowNumbers();
            });
            wrapper.querySelector('.js-collapse-row')?.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllRows();
            });
            wrapper.querySelector('.js-collapsed-view').addEventListener('click', () => openRow(wrapper));
            wrapper.querySelector('.js-expanded-view').addEventListener('click', (e) => e.stopPropagation());

            closeAllRows();
            list.prepend(wrapper);
            updateRowNumbers();

            const qtyInput = wrapper.querySelector('.js-input-qty');
            const cartonTextSpan = wrapper.querySelector('.js-carton-text');

            // تحديث دالة العرض لتظهر دائماً إذا كان للمنتج "كرتون"
            const updateCartonText = () => {
                const val = parseFloat(qtyInput.value) || 0;
                const cartonQty = parseInt(product.carton_qty) || 0;

                if (cartonQty > 0) {
                    const cartons = Math.floor(val / cartonQty);
                    const pieces = val % cartonQty;
                    const unitName = (product.is_splittable) ? 'طقم' : 'حبة';

                    // عرض معلومة الكرتون دائماً للتوضيح
                    let text = `الكرتون: ${cartonQty} ${unitName}`;
                    if (val > 0) {
                         text += ` | ${cartons > 0 ? cartons + ' كرتون' : ''} ${pieces > 0 ? 'و ' + pieces + ' ' + unitName : ''}`;
                    }
                    cartonTextSpan.textContent = text;
                } else {
                    cartonTextSpan.textContent = '';
                }
            };

            qtyInput.addEventListener('input', () => {
                updateCartonText();
                updateRowsSearch();
            });
            wrapper.querySelector('.js-input-notes')?.addEventListener('input', updateRowsSearch);

            if (initial.quantity_requested !== undefined) {
                wrapper.querySelector('.js-input-qty').value = initial.quantity_requested;
            }

            // استدعاء الدالة فوراً ليظهر نص الكرتون حتى قبل إدخال الكمية
            updateCartonText();

            if (initial.unit_type && hasUnitOptions) wrapper.querySelector(`select[name="items[${idx}][unit_type]"]`).value = initial.unit_type;
            if (initial.receipt_notes) wrapper.querySelector('.js-input-notes').value = initial.receipt_notes;

            if (!initial.product_id) wrapper.querySelector('.js-input-qty')?.focus();
        }
        function addCustomRow(initial = {}) {
            const idx = customIndex++;
            const wrapper = document.createElement('div');
            wrapper.dataset.isCustom = 'true';
            wrapper.className = 'js-item-row ui-card overflow-hidden mb-2';

            wrapper.innerHTML = `
                <div class="js-collapsed-view cursor-pointer p-3 text-sm ui-text-muted ui-surface-muted-bg transition flex justify-between items-center hidden">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="js-row-num ui-text-caption font-black ui-text-muted px-2 py-0.5 rounded ui-surface-muted-bg border ui-border"></span>
                        <span class="ui-text-caption ui-status-warning-bg border ui-border ui-status-warning px-1.5 py-0.5 rounded">مخصص</span>
                        <span class="js-col-name font-bold ui-title"></span>
                        <span class="ui-text-muted">|</span>
                        <span class="js-col-qty ui-status-success font-bold"></span>
                        <span class="js-col-notes ui-text-caption ui-status-warning hidden px-2 ui-status-warning-bg rounded border ui-border"></span>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <span class="js-col-owner-purchase hidden items-center rounded-full border ui-border ui-status-warning-bg px-2 py-0.5 ui-text-caption font-bold ui-status-warning">مشتريات مالك</span>
                        <span class="rounded-full border ui-border ui-surface-muted-bg px-2 py-0.5 ui-text-caption ui-text-muted" aria-hidden="true">▼</span>
                    </div>
                </div>

                <div class="js-expanded-view p-4 space-y-3 relative ui-surface-muted-bg">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="js-row-num ui-text-caption font-black ui-text-muted px-1.5 py-0.5 rounded ui-surface-muted-bg border ui-border"></span>
                            <span class="font-black ui-title text-base">منتج مخصص جديد</span>
                            <span class="ui-text-caption ui-status-warning-bg border ui-border ui-status-warning px-1.5 py-0.5 rounded">مخصص</span>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <span class="js-owner-purchase-badge hidden items-center rounded-full border ui-border ui-status-warning-bg px-2 py-0.5 ui-text-caption font-bold ui-status-warning">مشتريات مالك</span>
                            <button type="button" title="إغلاق السطر" aria-label="إغلاق السطر" class="js-collapse-row rounded-full border ui-border ui-surface-muted-bg px-2.5 py-1 ui-text-caption font-bold ui-text-muted transition">▲</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input name="custom_items[${idx}][custom_product_name]" required placeholder="اسم المنتج المخصص..." class="js-input-name w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">
                        <input name="custom_items[${idx}][quantity_requested]" type="number" step="0.01" min="0" placeholder="الكمية المطلوبة" class="js-input-qty w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">

                        <input name="custom_items[${idx}][cost_price_at_order]" type="number" step="0.01" min="0" placeholder="التكلفة (إن عرفت)" class="w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">
                        <select name="custom_items[${idx}][unit_type]" class="js-custom-unit ui-input text-sm">
                            <option value="unit">بدون تحديد</option>
                            <option value="roll">رول</option>
                            <option value="meter">متر</option>
                            <option value="piece">حبة</option>
                            <option value="kit">طقم</option>
                        </select>

                        <label class="js-custom-kit-details hidden">
                            <span class="mb-1 block ui-text-caption font-bold ui-text-muted">عدد حبات الطقم</span>
                            <input name="custom_items[${idx}][items_per_unit]" type="number" min="2" class="ui-input text-sm" placeholder="مثال: 6">
                        </label>
                        <label class="js-custom-roll-details hidden">
                            <span class="mb-1 block ui-text-caption font-bold ui-text-muted">طول الرول بالمتر</span>
                            <input name="custom_items[${idx}][roll_length]" type="number" step="0.01" min="0.01" class="ui-input text-sm" placeholder="مثال: 30">
                        </label>

                        <input name="custom_items[${idx}][receipt_notes]" maxlength="255" placeholder="ملاحظات إضافية (لون، مقاس...)" class="js-input-notes md:col-span-2 w-full rounded-lg ui-surface-muted-bg border ui-border ui-title px-3 py-2.5 text-sm focus:outline-none ">

                        <label class="md:col-span-2 flex items-center gap-2 rounded-lg border ui-border ui-status-warning-bg px-3 py-2 text-sm ui-status-warning">
                            <input type="checkbox" name="custom_items[${idx}][add_to_owner_purchases]" value="1" class="h-4 w-4 rounded ui-border ui-surface-muted-bg ui-status-warning ">
                            <span>مشتريات مالك / استهلاك</span>
                        </label>
                    </div>

                    <div class="pt-1">
                        <button type="button" class="js-remove ui-btn ui-btn-danger px-3 py-2 ui-text-caption">حذف السطر</button>
                    </div>
                </div>
            `;

            wrapper.querySelector('.js-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                wrapper.remove();
                updateRowNumbers();
            });
            wrapper.querySelector('.js-collapse-row')?.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllRows();
            });
            wrapper.querySelector('.js-collapsed-view').addEventListener('click', () => openRow(wrapper));
            wrapper.querySelector('.js-expanded-view').addEventListener('click', (e) => e.stopPropagation());

            closeAllRows();
            list.prepend(wrapper);
            updateRowNumbers();

            wrapper.querySelector('.js-input-name').value = initial.custom_product_name || '';
            wrapper.querySelector('.js-input-qty').value = initial.quantity_requested ?? '';

            const customUnitSelect = wrapper.querySelector(`select[name="custom_items[${idx}][unit_type]"]`);
            if (initial.unit_type) customUnitSelect.value = initial.unit_type;
            const kitDetails = wrapper.querySelector('.js-custom-kit-details');
            const rollDetails = wrapper.querySelector('.js-custom-roll-details');
            const syncCustomUnitDetails = () => {
                const unit = customUnitSelect.value;
                kitDetails.classList.toggle('hidden', unit !== 'kit');
                rollDetails.classList.toggle('hidden', unit !== 'roll');
                kitDetails.querySelector('input').toggleAttribute('required', unit === 'kit');
                rollDetails.querySelector('input').toggleAttribute('required', unit === 'roll');
            };
            const itemsPerUnitInput = wrapper.querySelector(`input[name="custom_items[${idx}][items_per_unit]"]`);
            const rollLengthInput = wrapper.querySelector(`input[name="custom_items[${idx}][roll_length]"]`);
            itemsPerUnitInput.value = initial.items_per_unit ?? '';
            rollLengthInput.value = initial.roll_length ?? '';
            customUnitSelect.addEventListener('change', syncCustomUnitDetails);
            syncCustomUnitDetails();

            const costInput = wrapper.querySelector(`input[name="custom_items[${idx}][cost_price_at_order]"]`);
            if (costInput && initial.cost_price_at_order !== undefined) {
                costInput.value = initial.cost_price_at_order;
            }

            const ownerPurchaseCheckbox = wrapper.querySelector(`input[name="custom_items[${idx}][add_to_owner_purchases]"]`);
            const ownerPurchaseBadge = wrapper.querySelector('.js-owner-purchase-badge');
            const collapsedOwnerPurchaseBadge = wrapper.querySelector('.js-col-owner-purchase');
            const syncOwnerPurchaseBadge = () => {
                const checked = Boolean(ownerPurchaseCheckbox?.checked);
                ownerPurchaseBadge?.classList.toggle('hidden', !checked);
                ownerPurchaseBadge?.classList.toggle('inline-flex', checked);
                collapsedOwnerPurchaseBadge?.classList.toggle('hidden', !checked);
                collapsedOwnerPurchaseBadge?.classList.toggle('inline-flex', checked);
                updateRowsSearch();
            };
            if (ownerPurchaseCheckbox) {
                ownerPurchaseCheckbox.checked = Boolean(initial.add_to_owner_purchases);
                ownerPurchaseCheckbox.addEventListener('change', syncOwnerPurchaseBadge);
            }
            syncOwnerPurchaseBadge();

            const nameInput = wrapper.querySelector('.js-input-name');
            const notesInput = wrapper.querySelector('.js-input-notes');
            nameInput?.addEventListener('input', updateRowsSearch);
            wrapper.querySelector('.js-input-qty')?.addEventListener('input', updateRowsSearch);
            if (notesInput) {
                notesInput.value = initial.receipt_notes || '';
                notesInput.addEventListener('input', updateRowsSearch);
            }
            updateRowsSearch();

            if (!initial.custom_product_name) wrapper.querySelector('.js-input-name')?.focus();
        }

        orderRowsSearch?.addEventListener('input', updateRowsSearch);

        input?.addEventListener('focus', () => {
            closeAllRows();
            renderMenu(input.value);
        });

        input?.addEventListener('input', () => {
            closeAllRows();
            renderMenu(input.value);
        });

        document.getElementById('addCustom')?.addEventListener('click', () => {
            if (preventAddingUntilCustomRowIsFilled()) {
                return;
            }
            input.value = '';
            menu.classList.add('hidden');
            addCustomRow();
        });

        menu?.addEventListener('click', (event) => {
            const button = event.target.closest('.js-pick-product');
            if (!button) return;
            const product = products.find((item) => String(item.id) === button.dataset.productId);
            if (!product) return;
            removeIncompleteCustomRows();

            addProductRow(product);
            input.value = '';
            menu.classList.add('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!document.getElementById('productPicker')?.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });

        document.getElementById('purchaseOrderForm')?.addEventListener('submit', async (event) => {
        event.preventDefault(); // إيقاف الإرسال الافتراضي

        // 1. التنظيف: إزالة الأسطر الفارغة تماماً قبل التحقق
        const allRows = Array.from(document.querySelectorAll('.js-item-row'));
        allRows.forEach(row => {
            const isCustom = row.dataset.isCustom === 'true';
            const qtyInput = row.querySelector('.js-input-qty');
            const nameInput = row.querySelector('.js-input-name');
            const notes = row.querySelector('.js-input-notes');

            const qtyValue = parseFloat(qtyInput?.value || '0');
            const nameValue = nameInput?.value?.trim() || '';
            const notesValue = notes?.value?.trim() || '';

            // لا نحذف المنتج النظامي المختار عند ترك الكمية فارغة؛ يجب أن يبقى ظاهرًا ويظهر عليه تنبيه الكمية.
            // نحذف فقط السطر المخصص الفارغ تمامًا لأنه لا يمثل منتجًا تم اختياره بعد.
            const isQtyEmpty = qtyValue <= 0;
            const isNameEmpty = isCustom && nameValue === '';

            if (isCustom && isQtyEmpty && isNameEmpty && notesValue === '') {
                row.remove();
            }
        });

        // 2. التحقق من صحة الأسطر المتبقية
        const rows = Array.from(document.querySelectorAll('.js-item-row'));
        if (rows.length === 0) {
            const message = 'يجب اختيار منتج واحد على الأقل أو إضافة منتج مخصص.';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    title: 'الطلبية فارغة',
                    text: message,
                    icon: 'warning',
                    confirmButtonText: 'حسنًا',
                    background: '',
                    color: '',
                    confirmButtonColor: '',
                });
            } else {
                window.alert(message);
            }
            input?.focus();
            return;
        }
        let hasErrors = false;
        let firstErrorRow = null;

        closeAllRows(); // إغلاق الكل لتسهيل رؤية الأخطاء

        rows.forEach((row) => {
            // إزالة الأخطاء القديمة
            row.classList.remove('ui-border', 'ui-border', 'ui-status-danger-bg');
            row.querySelectorAll('.js-error-badge').forEach(e => e.remove());

            const isCustom = row.dataset.isCustom === 'true';
            const qtyInput = row.querySelector('.js-input-qty');
            const nameInput = row.querySelector('.js-input-name');
            const unitSelect = row.querySelector('.js-input-unit');
            const productName = row.querySelector('.js-lbl-name')?.textContent || nameInput?.value || 'منتج';

            let rowErrors = [];

            // فحص الكمية (مطلوبة للجميع)
            if (!qtyInput || parseFloat(qtyInput.value || '0') <= 0) {
                rowErrors.push('الكمية');
            }

            // فحص الاسم (للمخصص فقط)
            if (isCustom && nameInput && !nameInput.value.trim()) {
                rowErrors.push('اسم المنتج');
            }

            // فحص النوع (للعادي فقط)
            if (!isCustom && unitSelect && unitSelect.tagName === 'SELECT' && !unitSelect.value) {
                rowErrors.push('نوع الوحدة');
            }

            // إذا وجد خطأ
            if (rowErrors.length > 0) {
                hasErrors = true;
                if (!firstErrorRow) firstErrorRow = row;

                // التلوين
                row.classList.add('ui-border', 'ui-border', 'ui-status-danger-bg');

                // إضافة رسالة الخطأ
                const badge = document.createElement('div');
                badge.className = 'js-error-badge mb-2 inline-flex items-center gap-1 rounded-full border ui-border ui-status-danger-bg px-3 py-1 ui-text-caption font-bold ui-status-danger';
                badge.innerHTML = `<span class="inline-block w-1.5 h-1.5 rounded-full ui-status-danger-bg"></span>يجب تحديد: ${rowErrors.join(' و ')} (${productName})`;

                const expandedView = row.querySelector('.js-expanded-view');
                expandedView.prepend(badge);

                // فتح السطر الملون ليراه المستخدم
                row.querySelector('.js-collapsed-view').classList.add('hidden');
                expandedView.classList.remove('hidden');
            }
        });

        if (hasErrors) {
            if (firstErrorRow) firstErrorRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const message = 'لا يمكن حفظ طلبية التوريد قبل تحديد الكمية المطلوبة لكل سطر. أدخل كمية أكبر من صفر للمنتجات النظامية أو مشتريات المالك أو المنتجات المخصصة، ثم أعد الحفظ.';
            if (typeof Swal !== 'undefined') {
                Swal.fire('بيانات ناقصة', message, 'error');
            } else {
                alert(message);
            }
            return;
        }

        if (skipConfirmation) {
            submissionInProgress = true;
            saveDraft(true);
            event.target.submit();
            return;
        }

        const result = typeof Swal !== 'undefined'
            ? await Swal.fire({
                title: isEdit ? 'تأكيد حفظ التعديلات' : 'تأكيد تجهيز الطلبية',
                text: isEdit ? 'سيتم حفظ التعديلات المحدثة.' : 'سيتم حفظ الطلبية كمسودة.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'نعم، حفظ',
                cancelButtonText: 'تراجع'
            })
            : { isConfirmed: window.confirm(isEdit ? 'هل تريد حفظ التعديلات؟' : 'هل تريد تجهيز الطلبية وحفظها؟') };

        if (result.isConfirmed) {
            submissionInProgress = true;
            saveDraft(true);
            event.target.submit();
        }
    });

        const draft = !isEdit ? readDraft() : null;
        const draftRestoreNotice = document.getElementById('draftRestoreNotice');
        const restoreStoredDraft = () => {
            if (!draft) return;
                restoringDraft = true;
                const supplierInput = document.querySelector('[name="supplier_name"]');
                const notesInput = document.querySelector('[name="notes"]');
                if (supplierInput) supplierInput.value = draft.supplier_name || '';
                if (notesInput) notesInput.value = draft.notes || '';
                (draft.product_rows || []).forEach((row) => {
                    const product = products.find((item) => Number(item.id) === Number(row.product_id));
                    if (product) addProductRow(product, row);
                });
                (draft.custom_rows || []).forEach((row) => addCustomRow(row));
                restoringDraft = false;
                closeAllRows();
                updateRowsSearch();
                saveDraft(false);
                draftRestoreNotice?.classList.add('hidden');
        };
        const openDraftRestoreDialog = async () => {
            if (!draft) return;
            const shouldRestore = typeof Swal !== 'undefined'
                ? (await Swal.fire({
                    title: 'استعادة مسودة الطلبية؟',
                    text: 'عُثر على بيانات محفوظة خلال آخر سبعة أيام.',
                    icon: 'question',
                    showCancelButton: true,
                    showCloseButton: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    confirmButtonText: 'استعادة المسودة',
                    cancelButtonText: 'إلغاء',
                })).isConfirmed
                : window.confirm('عُثر على مسودة طلبية محفوظة. هل تريد استعادتها؟');

            if (shouldRestore) restoreStoredDraft();
        };

        if (draft) {
            draftRestoreNotice?.classList.remove('hidden');
            draftRestoreNotice?.addEventListener('click', openDraftRestoreDialog);
        } else if (existingProductRows.length || existingCustomRows.length) {
            existingProductRows.forEach((row) => {
                const product = products.find((item) => Number(item.id) === Number(row.product_id));
                if (product) addProductRow(product, row);
            });
            existingCustomRows.forEach((row) => addCustomRow(row));
            closeAllRows();
            updateRowsSearch();
        }

        const purchaseOrderForm = document.getElementById('purchaseOrderForm');
        purchaseOrderForm?.addEventListener('input', scheduleDraftSave);
        purchaseOrderForm?.addEventListener('change', scheduleDraftSave);
        window.addEventListener('beforeunload', () => saveDraft(submissionInProgress));
    });
}
