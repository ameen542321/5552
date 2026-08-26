// تستخرج هذه الوحدة سلوك المبيعات اليومية كما هو؛ بيانات الصفحة والمسارات تصل عبر عقد Blade آمن.
const root = document.querySelector('[data-daily-sales-config]');

if (root) {
    const config = JSON.parse(root.dataset.dailySalesConfig || '{}');
    function toggleFinancialDetails(rowId) {
        const details = document.getElementById(`details-${rowId}`);
        const arrow = document.getElementById(`arrow-${rowId}`);
        if (!details) return;
        details.classList.toggle('hidden');
        arrow?.classList.toggle('rotate-180');
    }

    function toggleDetails(saleId) {
        const details = document.getElementById(`details-${saleId}`);
        const arrow = document.getElementById(`arrow-${saleId}`);

        if (details) {
            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                details.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }
    }

    const editableSales = config.editableSales || {};
    const autoEditSaleId = config.autoEditSaleId;
    const returnToAfterEdit = config.returnToAfterEdit;
    const editableProducts = config.editableProducts || [];
    const editFinancialUrlTemplate = config.editFinancialUrlTemplate;
    const editSaleUrlTemplate = config.editSaleUrlTemplate;
    let activeEditSale = null;
    let editSalePaymentTouched = false;
    let editSaleHasHigherReplacement = false;
    let editSaleConfirmedHigherReplacement = false;

    function markEditSalePaymentTouched() {
        editSalePaymentTouched = true;
        updateEditSaleFields(false);
    }

    function updateEditSaleFields(syncTotal = true) {
        const saleType = document.getElementById('edit-sale-type')?.value;
        const paidWrapper = document.getElementById('edit-paid-amount-wrapper');
        const debtWrapper = document.getElementById('edit-debt-wrapper');
        const employeeWrapper = document.getElementById('edit-employee-wrapper');
        const mixedWrapper = document.getElementById('edit-mixed-wrapper');
        const paidInput = document.getElementById('edit-paid-amount-input');
        const paidLabelText = document.getElementById('edit-paid-amount-label-text');
        const paidHelpButton = document.getElementById('edit-paid-amount-help-button');
        const conversionWarning = document.getElementById('edit-credit-conversion-warning');
        const mixedConversionWarning = document.getElementById('edit-mixed-conversion-warning');
        const mixedPaymentHelp = document.getElementById('edit-mixed-payment-help');
        const creditNoteWrapper = document.getElementById('edit-credit-note-wrapper');
        const registerCreditWrapper = document.getElementById('edit-register-credit-wrapper');
        const registerCreditInput = document.getElementById('edit-register-credit-input');
        const debtInput = document.getElementById('edit-debt-amount-input');
        const employeeInput = document.getElementById('edit-employee-input');
        const creditNoteInput = document.getElementById('edit-credit-note-input');
        const cashInput = document.getElementById('edit-cash-amount-input');
        const cardInput = document.getElementById('edit-card-amount-input');

        if (!saleType || !activeEditSale) return;

        const isCredit = saleType === 'credit';
        const isMixed = saleType === 'mixed';
        const originalSaleType = activeEditSale.sale_type || '';
        const originalPaidAmount = Number(activeEditSale.paid_amount || 0);
        const originalRemainingAmount = Number(activeEditSale.remaining_amount || 0);
        const originalHasCredit = originalSaleType === 'credit' || originalRemainingAmount > 0 || !!activeEditSale.has_partial_credit;
        const hasExistingPartialCredit = isMixed && originalHasCredit;
        const canRegisterRemainingAsCredit = !originalHasCredit && ['cash', 'card', 'mixed'].includes(saleType);
        const registerRemainingAsCredit = !!(registerCreditInput?.checked && canRegisterRemainingAsCredit);
        const hasDebtValue = Number(debtInput?.value || 0) > 0;
        const shouldShowEmployee = isCredit || registerRemainingAsCredit || hasExistingPartialCredit;
        const shouldShowDebt = isCredit || registerRemainingAsCredit || hasExistingPartialCredit;
        const isCollectedCreditConversion = originalSaleType === 'credit' && originalPaidAmount > 0 && originalRemainingAmount > 0 && !isCredit;
        const isCollectedCreditToMixedConversion = originalSaleType === 'credit' && originalPaidAmount > 0 && originalRemainingAmount > 0 && isMixed;

        paidWrapper?.classList.toggle('hidden', isCredit);
        registerCreditWrapper?.classList.toggle('hidden', !canRegisterRemainingAsCredit || isCredit);
        if (registerCreditInput && (!canRegisterRemainingAsCredit || isCredit)) registerCreditInput.checked = false;
        debtWrapper?.classList.toggle('hidden', !shouldShowDebt);
        employeeWrapper?.classList.toggle('hidden', !shouldShowEmployee);
        mixedWrapper?.classList.toggle('hidden', !isMixed);
        creditNoteWrapper?.classList.toggle('hidden', !(isCredit || isMixed || registerRemainingAsCredit || Number(activeEditSale.remaining_amount || 0) > 0));

        if (paidLabelText) {
            paidLabelText.textContent = isCollectedCreditConversion ? 'المبلغ المتبقي المطلوب تحصيله' : 'المبلغ المدفوع';
        }

        if (paidHelpButton) {
            paidHelpButton.dataset.help = isMixed
                ? 'في الميكس يجب أن يساوي المبلغ المدفوع مجموع الكاش والشبكة. أي أجل جزئي يحتاج اختيار موظف من القائمة.'
                : 'في عمليات النقد أو البطاقة يمكنك تعديل المبلغ المدفوع مباشرة.';
        }

        if (paidInput) {
            paidInput.readOnly = isMixed;
            paidInput.classList.toggle('opacity-70', isMixed);
            paidInput.classList.toggle('cursor-not-allowed', isMixed);
            paidInput.title = isMixed ? 'في الميكس يتم حساب المبلغ المدفوع تلقائياً من الكاش + الشبكة' : '';
        }

        mixedPaymentHelp?.classList.toggle('hidden', !isMixed);

        if (cashInput) cashInput.required = isMixed;
        if (cardInput) cardInput.required = isMixed;
        if (employeeInput) employeeInput.required = shouldShowEmployee;
        if (creditNoteInput) creditNoteInput.required = isCredit || registerRemainingAsCredit || (isMixed && hasDebtValue);

        conversionWarning?.classList.toggle('hidden', !isCollectedCreditConversion);
        mixedConversionWarning?.classList.toggle('hidden', !isCollectedCreditToMixedConversion);

        if (paidInput && isCollectedCreditConversion) {
            paidInput.value = originalRemainingAmount.toFixed(2);
        }

        if (mixedConversionWarning && isCollectedCreditToMixedConversion) {
            mixedConversionWarning.innerHTML = `عند التحويل إلى ميكس من آجل محصّل جزئيًا: تم تحصيل <span class="font-bold">${originalPaidAmount.toFixed(2)}</span> سابقًا، والمتبقي الآن <span class="font-bold">${originalRemainingAmount.toFixed(2)}</span>. أدخل القيم بحيث يكون <span class="font-bold">كاش + شبكة + أجل = ${originalRemainingAmount.toFixed(2)}</span>.`;
        }

        if (registerRemainingAsCredit && debtInput && !isMixed) {
            const finalTotal = calculateEditedOperationTotal();
            const paidValue = Number(paidInput?.value || 0);
            debtInput.value = Math.max(0, finalTotal - paidValue).toFixed(2);
        }

        if (syncTotal) {
            syncEditedOperationTotal();
        }
    }

    function fillEditSaleForm(sale, oldValues = null) {
        const values = oldValues ? {...sale, ...oldValues} : sale;
        const setValue = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.value = value ?? '';
        };

        setValue('edit-sale-type', values.edit_sale_type || values.sale_type);
        setValue(
            'edit-paid-amount-input',
            oldValues ? values.paid_amount : values.paid_amount
        );
        setValue('edit-debt-amount-input', values.debt_amount ?? values.remaining_amount);
        setValue('edit-employee-input', values.employee_id);
        setValue('edit-cash-amount-input', Number(values.cash_amount || 0) > 0 ? values.cash_amount : '');
        setValue('edit-card-amount-input', Number(values.card_amount || 0) > 0 ? values.card_amount : '');
        setValue('edit-labor-total-input', values.labor_total);
        setValue('edit-description-input', values.description);
        setValue('edit-credit-note-input', values.operation_name ?? values.credit_note);
        const effectiveSaleType = values.edit_sale_type || values.sale_type;
        const registerCreditInput = document.getElementById('edit-register-credit-input');
        if (registerCreditInput) {
            registerCreditInput.checked = oldValues
                ? !!oldValues.record_remaining_as_credit
                : effectiveSaleType === 'mixed' && Number(values.remaining_amount || 0) > 0;
        }
        const hasCreditNote = !!(values.operation_name || values.credit_note || effectiveSaleType === 'credit' || Number(values.remaining_amount || 0) > 0);
        document.getElementById('edit-credit-note-wrapper')?.classList.toggle('hidden', !hasCreditNote);
        document.getElementById('edit-meter-products-note')?.classList.toggle('hidden', !sale.has_meter_product);
        renderEditSaleItems(sale.items || [], oldValues);
    }

    function escapeEditSaleHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&;');
    }

    function getEditUnitMeta(productLike, unitType = 'unit') {
        const isSplittable = Number(productLike?.is_splittable || 0) === 1 || productLike?.is_splittable === true;
        const itemsPerUnit = Math.max(1, Number(productLike?.items_per_unit || 1));
        const availableUnits = Math.max(0, Number(productLike?.quantity ?? productLike?.available_quantity ?? 0));
        const basePrice = Number(productLike?.price ?? productLike?.base_price ?? 0);
        const piecePrice = Number(productLike?.piece_price || (isSplittable ? (basePrice / itemsPerUnit) : basePrice));
        const normalizedUnit = isSplittable && unitType === 'piece' ? 'piece' : 'unit';

        return {
            isSplittable,
            unitType: normalizedUnit,
            label: normalizedUnit === 'piece' ? 'حبة' : (isSplittable ? 'طقم' : 'وحدة'),
            price: normalizedUnit === 'piece' ? piecePrice : basePrice,
            maxQuantity: normalizedUnit === 'piece' ? Math.floor(availableUnits * itemsPerUnit) : availableUnits,
        };
    }

    function renderEditUnitSelector(productLike, selectedUnit = 'unit') {
        const meta = getEditUnitMeta(productLike, selectedUnit);
        if (!meta.isSplittable) {
            return '<input type="hidden" class="edit-sale-item-unit-type" name="item_unit_types[]" value="unit">';
        }

        return `
            <label class="ui-text-caption ui-text-muted block mb-1">طريقة البيع</label>
            <select name="item_unit_types[]" data-current-unit="${meta.unitType}" data-sensitive-input="daily.unit-change"
                    class="edit-sale-item-unit-type w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title ui-text-caption">
                <option value="unit" ${meta.unitType === 'unit' ? 'selected' : ''}>بالطقم</option>
                <option value="piece" ${meta.unitType === 'piece' ? 'selected' : ''}>بالحبة</option>
            </select>
        `;
    }

    function editQuantityUnitLabel(unitType, isSplittable = false) {
        if (!isSplittable) return 'حبة';
        return unitType === 'piece' ? 'حبة' : 'طقم';
    }

    function renderEditQuantityControl(quantity, maxQuantity = 0, isFractional = false, unitType = 'unit', quantityLock = '', quantityStyle = '', isSplittable = false) {
        const maxAttribute = Number(maxQuantity) > 0 ? `max="${Number(maxQuantity).toFixed(2)}"` : '';
        if (isFractional) {
            return `<input type="number" step="0.01" min="0.01" ${maxAttribute}
                           name="item_quantities[]" value="${escapeEditSaleHtml(quantity)}" ${quantityLock}
                           data-sensitive-input="daily.quantity-limit"
                           class="edit-sale-item-quantity w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title ${quantityStyle}">`;
        }

        return `
            <div class="flex items-stretch gap-2" dir="ltr">
                <button type="button" data-sensitive-action="daily.quantity-adjust" data-direction="-1" aria-label="إنقاص الكمية"
                        class="ui-btn ui-btn-secondary min-w-10 px-3 py-2">−</button>
                <div class="flex min-w-0 flex-1 items-center rounded-lg border ui-border ui-surface-muted-bg px-2" dir="rtl">
                    <input type="number" step="1" min="1" ${maxAttribute} name="item_quantities[]" value="${escapeEditSaleHtml(quantity)}"
                           data-sensitive-input="daily.quantity-limit"
                           class="edit-sale-item-quantity min-w-0 flex-1 bg-transparent px-1 py-2 text-center ui-title">
                    <span class="edit-sale-quantity-unit shrink-0 ui-text-caption ui-text-muted">${editQuantityUnitLabel(unitType, isSplittable)}</span>
                </div>
                <button type="button" data-sensitive-action="daily.quantity-adjust" data-direction="1" aria-label="زيادة الكمية"
                        class="ui-btn ui-btn-secondary min-w-10 px-3 py-2">+</button>
            </div>`;
    }

    function renderEditSaleItems(items, oldValues = null) {
        const section = document.getElementById('edit-sale-items-section');
        const list = document.getElementById('edit-sale-items-list');
        if (!section || !list) return;

        if (!items.length) {
            list.innerHTML = '<div class="edit-sale-empty-items rounded-lg ui-surface-muted-bg p-4 text-center"><i class="fa-solid fa-box-open mb-2 ui-text-muted"></i><p class="font-bold ui-title">لا توجد منتجات في العملية</p><p class="mt-1 ui-text-caption ui-text-muted">تحتوي هذه العملية على شغل يد فقط. يمكنك إضافة منتج عند الحاجة.</p></div>';
            section.classList.remove('hidden');
            return;
        }

        const oldIds = Array.isArray(oldValues?.item_ids) ? oldValues.item_ids.map(String) : [];
        const oldQuantities = Array.isArray(oldValues?.item_quantities) ? oldValues.item_quantities : [];
        const oldPrices = Array.isArray(oldValues?.item_prices) ? oldValues.item_prices : [];
        const oldUnitTypes = Array.isArray(oldValues?.item_unit_types) ? oldValues.item_unit_types : [];

        list.innerHTML = items.map((item, index) => {
            const oldIndex = oldIds.indexOf(String(item.id));
            const quantity = oldIndex >= 0 ? oldQuantities[oldIndex] : item.quantity;
            const price = oldIndex >= 0 ? oldPrices[oldIndex] : item.price;
            const oldUnitType = oldIndex >= 0 ? oldUnitTypes[oldIndex] : item.unit_type;
            const quantityLock = item.is_fractional
                ? 'readonly aria-readonly="true" title="كمية الرول محفوظة حسب الاستهلاك بالأمتار ولا تعدل من هنا"'
                : '';
            const availableQuantity = Number(item.available_quantity || 0);
            const selectedUnitType = oldUnitType === 'piece' ? 'piece' : 'unit';
            const currentStockUnits = selectedUnitType === 'piece'
                ? Number(quantity || 0) / Math.max(1, Number(item.items_per_unit || 1))
                : Number(quantity || 0);
            const unitMeta = getEditUnitMeta({ ...item, quantity: availableQuantity + currentStockUnits, price: item.base_price || price }, selectedUnitType);
            const maxQuantity = item.is_fractional ? Number(quantity || 0) : unitMeta.maxQuantity;
            const quantityStyle = item.is_fractional ? 'opacity-60 cursor-not-allowed' : '';

            return `
                <div class="edit-sale-item-row rounded-lg border ui-border ui-surface-muted-bg p-3"
                     data-product-price="${Number(item.base_price || price || 0).toFixed(2)}"
                     data-product-piece-price="${Number(item.piece_price || 0).toFixed(2)}"
                     data-product-items-per-unit="${Number(item.items_per_unit || 1)}"
                     data-product-available-units="${Number(availableQuantity + currentStockUnits).toFixed(4)}"
                     data-product-is-splittable="${item.is_splittable ? '1' : '0'}">
                    <input type="hidden" name="item_ids[]" value="${Number(item.id)}">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <p class="text-sm font-bold ui-title">${escapeEditSaleHtml(item.name)}</p>
                            <p class="ui-text-caption ui-text-muted ">الوحدة المعروضة: ${escapeEditSaleHtml(item.unit)} — الإجمالي الحالي: ${Number(item.total || 0).toFixed(2)} ر.س</p>
                        </div>
                        <button type="button" data-sensitive-action="daily.item-remove" class="ui-btn ui-btn-danger px-2 py-2 ui-text-caption">حذف السطر</button>
                    </div>
                    <div class="mb-2 edit-sale-product-picker" data-current-product-id="${Number(item.product_id)}" data-current-price="${Number(price || 0).toFixed(2)}">
                        <label class="ui-text-caption ui-text-muted block mb-1">المنتج</label>
                        <input type="hidden" class="edit-sale-item-product-id" name="item_product_ids[]" value="${Number(item.product_id)}">
                        <div class="mb-2 rounded-lg border ui-border ui-surface-muted-bg px-3 py-2 ui-text-caption ui-text-muted">
                            المنتج المحدد: <span class="edit-sale-selected-product-name font-bold ui-title">${escapeEditSaleHtml(item.name)}</span>
                        </div>
                        <input type="search" placeholder="اكتب اسم المنتج فيظهر مباشرة..." ${item.is_fractional ? 'disabled' : ''}
                               data-sensitive-input="daily.product-search"
                               class="edit-sale-product-search mb-2 w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title placeholder:ui-text-muted ui-text-caption ${item.is_fractional ? 'opacity-60 cursor-not-allowed' : ''}">
                        <div class="edit-sale-product-results hidden max-h-48 overflow-y-auto rounded-lg border ui-border ui-surface-muted-bg"></div>
                        <p class="edit-sale-product-search-empty hidden mt-1 ui-text-caption ui-status-warning ">لا توجد منتجات مطابقة للبحث.</p>
                        ${item.is_fractional ? `<p class="mt-1 ui-text-caption ui-status-warning ">لا يمكن استبدال منتجات الرول/التضليل من هذه النافذة.</p>` : ''}
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <div>
                            ${item.is_fractional ? '<input type="hidden" class="edit-sale-item-unit-type" name="item_unit_types[]" value="unit">' : renderEditUnitSelector({ ...item, quantity: availableQuantity + currentStockUnits, price: item.base_price || price }, selectedUnitType)}
                        </div>
                        <div>
                            <label class="ui-text-caption ui-text-muted block mb-1">الكمية</label>
                            ${renderEditQuantityControl(quantity, maxQuantity, item.is_fractional, selectedUnitType, quantityLock, quantityStyle, item.is_splittable)}
                        </div>
                        <div>
                            <label class="ui-text-caption ui-text-muted block mb-1">سعر البيع</label>
                            <input type="number" step="0.01" min="0" name="item_prices[]" value="${escapeEditSaleHtml(price)}"
                                   data-sensitive-input="daily.operation-total"
                                   class="edit-sale-item-price w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                            <p class="mt-1 ui-text-caption ui-status-info">الإجمالي: <span class="edit-line-total">${(Number(quantity || 0) * Number(price || 0)).toFixed(2)}</span> ر.س</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        section.classList.remove('hidden');
    }

    function addEditSaleProductRow() {
        const list = document.getElementById('edit-sale-items-list');
        if (!list) return;

        list.querySelector('.edit-sale-empty-items')?.remove();

        const row = document.createElement('div');
        row.className = 'edit-sale-item-row rounded-lg border ui-border ui-surface-muted-bg p-3';
        row.innerHTML = `
            <input type="hidden" name="item_ids[]" value="0">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                    <p class="text-sm font-bold ui-title">منتج جديد</p>
                    <p class="ui-text-caption ui-text-muted">اختر المنتج واكتب الكمية والسعر.</p>
                </div>
                <button type="button" data-sensitive-action="daily.item-remove" class="ui-btn ui-btn-danger px-2 py-2 ui-text-caption">حذف السطر</button>
            </div>
            <div class="mb-2 edit-sale-product-picker" data-current-product-id="0" data-current-price="0">
                <label class="ui-text-caption ui-text-muted block mb-1">المنتج</label>
                <input type="hidden" class="edit-sale-item-product-id" name="item_product_ids[]" value="">
                <div class="mb-2 rounded-lg border ui-border ui-surface-muted-bg px-3 py-2 ui-text-caption ui-text-muted">
                    المنتج المحدد: <span class="edit-sale-selected-product-name font-bold ui-title">لم يتم الاختيار</span>
                </div>
                <input type="search" placeholder="اكتب اسم المنتج فيظهر مباشرة..."
                       data-sensitive-input="daily.product-search"
                       class="edit-sale-product-search mb-2 w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title placeholder:ui-text-muted ui-text-caption">
                <div class="edit-sale-product-results hidden max-h-48 overflow-y-auto rounded-lg border ui-border ui-surface-muted-bg"></div>
                <p class="edit-sale-product-search-empty hidden mt-1 ui-text-caption ui-status-warning">لا توجد منتجات مطابقة للبحث.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <div class="edit-sale-unit-wrapper">
                    <input type="hidden" class="edit-sale-item-unit-type" name="item_unit_types[]" value="unit">
                </div>
                <div>
                    <label class="ui-text-caption ui-text-muted block mb-1">الكمية</label>
                    ${renderEditQuantityControl(1, 0, false, 'unit')}
                </div>
                <div>
                    <label class="ui-text-caption ui-text-muted block mb-1">سعر البيع</label>
                    <input type="number" step="0.01" min="0" name="item_prices[]" value="0" data-sensitive-input="daily.operation-total"
                           class="edit-sale-item-price w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title">
                    <p class="mt-1 ui-text-caption ui-status-info">الإجمالي: <span class="edit-line-total">0.00</span> ر.س</p>
                </div>
            </div>`;
        list.appendChild(row);
        row.querySelector('.edit-sale-product-search')?.focus();
        syncEditedOperationTotal();
    }

    function removeEditSaleItemRow(button) {
        button?.closest('.edit-sale-item-row')?.remove();
        syncEditedOperationTotal();
    }


    function renderEditProductSearchResults(searchInput) {
        const picker = searchInput?.closest('.edit-sale-product-picker');
        const results = picker?.querySelector('.edit-sale-product-results');
        const emptyMessage = picker?.querySelector('.edit-sale-product-search-empty');
        if (!picker || !results) return;
        closeEditProductResults(picker);

        const term = String(searchInput.value || '').trim().toLowerCase();
        const matches = editableProducts
            .filter((product) => product.product_type !== 'fractional')
            .filter((product) => {
                const haystack = `${product.name || ''} ${Number(product.price || 0).toFixed(2)}`.toLowerCase();
                return !term || haystack.includes(term);
            })
            .slice(0, 12);

        results.innerHTML = matches.map((product) => {
            const quantity = Number(product.quantity || 0);
            const isEnded = quantity <= 0;
            const productMeta = getEditUnitMeta(product, product.quick_sale_default_unit === 'piece' ? 'piece' : 'unit');
            const priceLabel = productMeta.isSplittable
                ? `حبة ${Number(product.piece_price || productMeta.price || 0).toFixed(2)} / طقم ${Number(product.price || 0).toFixed(2)}`
                : `${Number(product.price || 0).toFixed(2)}`;
            return `
            <button type="button"
                    class="w-full text-right px-3 py-2 ui-text-caption border-b ui-border last:ui-border ${isEnded ? 'cursor-not-allowed ui-text-muted ui-surface-muted-bg' : 'ui-text-muted ui-status-info-bg'}"
                    ${isEnded ? 'disabled aria-disabled="true"' : 'data-sensitive-action="daily.product-choose"'}
                    data-product-id="${Number(product.id)}"
                    data-product-name="${escapeEditSaleHtml(product.name)}"
                    data-product-price="${Number(product.price || 0).toFixed(2)}"
                    data-piece-price="${Number(product.piece_price || 0).toFixed(2)}"
                    data-items-per-unit="${Number(product.items_per_unit || 1)}"
                    data-is-splittable="${Number(product.is_splittable || 0)}"
                    data-default-unit="${product.quick_sale_default_unit === 'piece' ? 'piece' : 'unit'}"
                    data-quantity="${quantity.toFixed(2)}">
                <span class="block font-bold ${isEnded ? 'ui-text-muted' : 'ui-title'}">${escapeEditSaleHtml(product.name)} ${isEnded ? '<span class="ui-status-danger">(منتهي)</span>' : ''}</span>
                <span class="ui-text-muted">السعر: ${priceLabel} — المتاح: ${quantity.toFixed(2)}</span>
            </button>
        `}).join('');

        results.classList.toggle('hidden', matches.length === 0);
        emptyMessage?.classList.toggle('hidden', matches.length > 0);
    }

    function chooseEditProduct(button) {
        const picker = button?.closest('.edit-sale-product-picker');
        const row = picker?.closest('.edit-sale-item-row');
        const productIdInput = picker?.querySelector('.edit-sale-item-product-id');
        const selectedName = picker?.querySelector('.edit-sale-selected-product-name');
        const priceInput = row?.querySelector('.edit-sale-item-price');
        const searchInput = picker?.querySelector('.edit-sale-product-search');
        const results = picker?.querySelector('.edit-sale-product-results');
        if (!picker || !productIdInput || !priceInput) return;

        const isNewSaleItem = Number(row?.querySelector('input[name="item_ids[]"]')?.value || 0) === 0;
        const oldPrice = Number(priceInput.value || picker.dataset.currentPrice || 0);
        const selectedUnit = button.dataset.defaultUnit === 'piece' ? 'piece' : 'unit';
        const productLike = {
            price: Number(button.dataset.productPrice || 0),
            piece_price: Number(button.dataset.piecePrice || 0),
            quantity: Number(button.dataset.quantity || 0),
            items_per_unit: Number(button.dataset.itemsPerUnit || 1),
            is_splittable: Number(button.dataset.isSplittable || 0),
        };
        const unitMeta = getEditUnitMeta(productLike, selectedUnit);
        const newPrice = unitMeta.price;
        productIdInput.value = button.dataset.productId || productIdInput.value;
        if (row) {
            row.dataset.productPrice = Number(button.dataset.productPrice || 0).toFixed(2);
            row.dataset.productPiecePrice = Number(button.dataset.piecePrice || 0).toFixed(2);
            row.dataset.productItemsPerUnit = Number(button.dataset.itemsPerUnit || 1);
            row.dataset.productAvailableUnits = Number(button.dataset.quantity || 0).toFixed(4);
            row.dataset.productIsSplittable = Number(button.dataset.isSplittable || 0);
            row.querySelector('.edit-sale-unit-wrapper, .edit-sale-item-unit-type')?.closest('div')?.replaceChildren(
                ...htmlToNodes(renderEditUnitSelector(productLike, selectedUnit))
            );
        }
        const quantityInput = row?.querySelector('.edit-sale-item-quantity');
        if (quantityInput) {
            quantityInput.max = unitMeta.maxQuantity.toFixed(2);
            enforceEditQuantityLimit(quantityInput);
        }
        const quantityUnit = row?.querySelector('.edit-sale-quantity-unit');
        if (quantityUnit) quantityUnit.textContent = editQuantityUnitLabel(selectedUnit, unitMeta.isSplittable);
        if (selectedName) selectedName.textContent = button.dataset.productName || '';
        if (searchInput) searchInput.value = button.dataset.productName || '';
        if (results) results.classList.add('hidden');

        priceInput.value = newPrice.toFixed(2);
        if (!isNewSaleItem) showReplacementPriceWarnings(oldPrice, newPrice);
        syncEditedOperationTotal();
    }

    function htmlToNodes(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        return [...template.content.childNodes];
    }

    function handleEditUnitTypeChange(select) {
        const row = select?.closest('.edit-sale-item-row');
        if (!row) return;

        const productLike = {
            price: Number(row.dataset.productPrice || 0),
            piece_price: Number(row.dataset.productPiecePrice || 0),
            quantity: Number(row.dataset.productAvailableUnits || 0),
            items_per_unit: Number(row.dataset.productItemsPerUnit || 1),
            is_splittable: Number(row.dataset.productIsSplittable || 0),
        };
        const previousUnit = select.dataset.currentUnit === 'piece' ? 'piece' : 'unit';
        const nextUnit = select.value === 'piece' ? 'piece' : 'unit';
        const unitMeta = getEditUnitMeta(productLike, nextUnit);
        const quantityInput = row.querySelector('.edit-sale-item-quantity');
        const priceInput = row.querySelector('.edit-sale-item-price');
        if (quantityInput) {
            const currentQuantity = Math.max(1, Number(quantityInput.value || 1));
            const itemsPerUnit = Math.max(1, Number(row.dataset.productItemsPerUnit || 1));

            if (previousUnit === 'unit' && nextUnit === 'piece') {
                quantityInput.value = String(currentQuantity * itemsPerUnit);
            } else if (previousUnit === 'piece' && nextUnit === 'unit') {
                const convertedQuantity = currentQuantity / itemsPerUnit;
                if (!Number.isInteger(convertedQuantity)) {
                    select.value = previousUnit;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'لا يمكن التحويل إلى طقم',
                            text: `${currentQuantity} حبة لا تكوّن أطقمًا كاملة؛ الطقم يحتوي على ${itemsPerUnit} حبة.`,
                        });
                    }
                    return;
                }
                quantityInput.value = String(convertedQuantity);
            }

            quantityInput.max = unitMeta.maxQuantity.toFixed(2);
            enforceEditQuantityLimit(quantityInput);
        }
        select.dataset.currentUnit = nextUnit;
        const quantityUnit = row.querySelector('.edit-sale-quantity-unit');
        if (quantityUnit) quantityUnit.textContent = editQuantityUnitLabel(nextUnit, unitMeta.isSplittable);
        if (priceInput) priceInput.value = unitMeta.price.toFixed(2);
        syncEditedOperationTotal();
    }

    function adjustEditSaleQuantity(button, change) {
        const row = button?.closest('.edit-sale-item-row');
        const input = row?.querySelector('.edit-sale-item-quantity');
        if (!input || input.readOnly) return;

        const currentValue = Math.max(1, Number(input.value || 1));
        input.value = String(Math.max(1, currentValue + Number(change || 0)));
        enforceEditQuantityLimit(input);
        syncEditedOperationTotal();
    }

    function closeEditProductResults(exceptPicker = null) {
        document.querySelectorAll('.edit-sale-product-results').forEach((results) => {
            if (exceptPicker && exceptPicker.contains(results)) return;
            results.classList.add('hidden');
        });
    }


    function setEditWarning(id, message) {
        const element = document.getElementById(id);
        if (!element) return;
        element.textContent = message || '';
        element.classList.toggle('hidden', !message);
    }

    function showReplacementPriceWarnings(oldPrice, newPrice) {
        const increased = newPrice > oldPrice;
        editSaleHasHigherReplacement = editSaleHasHigherReplacement || increased;
        const comparisonText = increased
            ? `سعر المنتج الجديد (${newPrice.toFixed(2)}) أعلى من السعر السابق (${oldPrice.toFixed(2)}).`
            : `سعر المنتج الجديد (${newPrice.toFixed(2)}) نفس السعر السابق أو أقل منه (${oldPrice.toFixed(2)}).`;

        setEditWarning(
            'edit-paid-price-change-warning',
            increased
                ? `${comparisonText} إذا لم تعدل المبلغ المدفوع يدوياً فسيتم اعتماد سعر بيع المنتج الجديد عند الحفظ.`
                : `${comparisonText} سيتم اعتماد ما تكتبه في حقل المدفوع.`
        );
        setEditWarning(
            'edit-mixed-price-change-warning',
            `تنبيه الميكس: ${comparisonText} راجع توزيع الكاش والشبكة والأجل قبل الحفظ.`
        );
        setEditWarning(
            'edit-labor-price-change-warning',
            'تنبيه: تغيير المنتج لا يغير شغل اليد تلقائياً؛ عدّل شغل اليد إذا كان مرتبطاً بالمنتج الجديد.'
        );
    }

    function syncEditedProductPrice(selectElement) {
        const selectedOption = selectElement?.selectedOptions?.[0];
        const row = selectElement?.closest('.rounded-lg');
        const priceInput = row?.querySelector('.edit-sale-item-price');
        const oldPrice = Number(priceInput?.value || 0);
        const newPrice = Number(selectedOption?.dataset.price || 0);
        if (selectedOption && priceInput) {
            priceInput.value = newPrice.toFixed(2);
            showReplacementPriceWarnings(oldPrice, newPrice);
        }
        syncEditedOperationTotal();
    }

    function calculateEditedOperationTotal() {
        if (!activeEditSale) return 0;

        const quantities = [...document.querySelectorAll('.edit-sale-item-quantity')];
        const prices = [...document.querySelectorAll('.edit-sale-item-price')];
        if (!quantities.length || quantities.length !== prices.length) {
            return Number(activeEditSale.operation_amount ?? activeEditSale.final_total ?? activeEditSale.paid_amount ?? 0);
        }

        const productsTotal = quantities.reduce((total, quantityInput, index) => {
            const quantity = Number(quantityInput.value || 0);
            const price = Number(prices[index]?.value || 0);
            const lineTotal = quantity * price;
            quantityInput.closest('.edit-sale-item-row')?.querySelector('.edit-line-total')?.replaceChildren(document.createTextNode(lineTotal.toFixed(2)));
            return total + lineTotal;
        }, 0);
        const taxRate = Number(activeEditSale.tax_rate || 0);
        const laborTotal = Number(document.getElementById('edit-labor-total-input')?.value || 0);
        return productsTotal + (productsTotal * taxRate / 100) + laborTotal;
    }

    function enforceEditQuantityLimit(input) {
        const max = Number(input?.max || 0);
        const value = Number(input?.value || 0);
        if (input?.value !== '' && value < 1 && Number(input?.step || 1) >= 1) {
            input.value = '1';
            return;
        }
        if (max > 0 && value > max) {
            input.value = max.toFixed(2).replace(/\.00$/, '');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'الكمية غير متاحة', text: `أقصى كمية متاحة لهذا السطر هي ${max.toFixed(2)}` });
            }
        }
    }

    function syncEditedOperationTotal() {
        if (!activeEditSale) return;

        const finalTotal = calculateEditedOperationTotal();
        const saleType = document.getElementById('edit-sale-type')?.value;

        if (saleType === 'cash' || saleType === 'card') {
            const paidInput = document.getElementById('edit-paid-amount-input');
            const debtInput = document.getElementById('edit-debt-amount-input');
            const registerCreditInput = document.getElementById('edit-register-credit-input');
            if (paidInput && !editSalePaymentTouched && !registerCreditInput?.checked) paidInput.value = finalTotal.toFixed(2);
            if (debtInput && registerCreditInput?.checked) debtInput.value = Math.max(0, finalTotal - Number(paidInput?.value || 0)).toFixed(2);
        } else if (saleType === 'credit') {
            const debtInput = document.getElementById('edit-debt-amount-input');
            if (debtInput) debtInput.value = finalTotal.toFixed(2);
        } else if (saleType === 'mixed') {
            syncMixedPaidAmountFromSplit();
        }
    }

    function openEditSaleModal(saleId, oldValues = null) {
        const sale = (saleId && typeof saleId === 'object') ? saleId : editableSales[String(saleId)];
        const modal = document.getElementById('edit-sale-modal');
        const form = document.getElementById('edit-sale-form');
        const title = document.getElementById('edit-sale-modal-title');

        if (!sale || !modal || !form) return;

        activeEditSale = sale;
        editSalePaymentTouched = false;
        editSaleHasHigherReplacement = false;
        editSaleConfirmedHigherReplacement = false;
        setEditWarning('edit-paid-price-change-warning', '');
        setEditWarning('edit-mixed-price-change-warning', '');
        setEditWarning('edit-labor-price-change-warning', '');
        const editAction = editSaleUrlTemplate.replace('__SALE_ID__', sale.id);
        form.action = returnToAfterEdit
            ? `${editAction}?return_to=${encodeURIComponent(returnToAfterEdit)}`
            : editAction;
        if (title) title.textContent = `تعديل العملية #${sale.id}`;
        fillEditSaleForm(sale, oldValues);
        modal.classList.remove('hidden');
        // لا نعيد حساب المبلغ عند مجرد فتح النافذة؛ يجب إظهار المبلغ الكامل
        // المحفوظ للعملية، ثم يعاد الحساب فقط عند تعديل النوع أو المنتجات.
        updateEditSaleFields(false);
    }

    function closeEditSaleModal() {
        const modal = document.getElementById('edit-sale-modal');
        if (modal) modal.classList.add('hidden');
        activeEditSale = null;
    }


    function openFinancialEditModal(operation) {
        const modal = document.getElementById('edit-financial-modal');
        const form = document.getElementById('edit-financial-form');
        if (!modal || !form || !operation) return;

        form.action = editFinancialUrlTemplate
            .replace('__TYPE__', operation.route_key)
            .replace('__ID__', operation.id);
        document.getElementById('edit-financial-modal-title').textContent = `تعديل ${operation.type_label}`;
        document.getElementById('edit-financial-amount').value = Number(operation.amount || 0).toFixed(2);
        document.getElementById('edit-financial-date').value = operation.date || '';
        document.getElementById('edit-financial-description').value = operation.description || '';
        modal.classList.remove('hidden');
    }

    function closeFinancialEditModal() {
        const modal = document.getElementById('edit-financial-modal');
        if (modal) modal.classList.add('hidden');
    }

    function syncMixedPaidAmountFromSplit() {
        const saleType = document.getElementById('edit-sale-type')?.value;
        if (saleType !== 'mixed') return;

        const paidInput = document.getElementById('edit-paid-amount-input');
        const cashAmount = Math.max(0, Number(document.getElementById('edit-cash-amount-input')?.value || 0));
        const cardAmount = Math.max(0, Number(document.getElementById('edit-card-amount-input')?.value || 0));
        if (paidInput) paidInput.value = (cashAmount + cardAmount).toFixed(2);
        updateEditSaleFields(false);
    }

    function syncMixedSplitFromPaidAmount() {
        const saleType = document.getElementById('edit-sale-type')?.value;
        if (saleType !== 'mixed') return;

        const paidInput = document.getElementById('edit-paid-amount-input');
        const cashInput = document.getElementById('edit-cash-amount-input');
        const cardInput = document.getElementById('edit-card-amount-input');
        if (!paidInput || !cashInput) return;

        const paidAmount = Math.max(0, Number(paidInput.value || 0));
        const cardAmount = Math.max(0, Number(cardInput?.value || 0));
        cashInput.value = Math.max(0, paidAmount - cardAmount).toFixed(2);
        updateEditSaleFields(false);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const filterForm = document.getElementById('daily-sales-filter-form');
        const dateInput = document.getElementById('daily-sales-date-input');
        const searchInput = document.getElementById('daily-sales-search-input');
        let searchSubmitTimer = null;

        if (filterForm && dateInput) {
            dateInput.addEventListener('change', () => filterForm.submit());
        }

        if (filterForm && searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchSubmitTimer);
                searchSubmitTimer = setTimeout(() => filterForm.submit(), 600);
            });
        }

        document.getElementById('edit-sale-modal')?.addEventListener('click', (event) => {
            if (!event.target.closest('.edit-sale-product-picker')) {
                closeEditProductResults();
            }
        });


        if (autoEditSaleId && editableSales[String(autoEditSaleId)]) {
            openEditSaleModal(autoEditSaleId);
        }

        document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                event.stopPropagation();
                const message = form.dataset.confirmDelete || 'هل تريد حذف هذا السجل؟';

                if (typeof Swal === 'undefined') {
                    if (window.confirm(message)) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                    return;
                }

                const result = await Swal.fire({
                    title: 'تأكيد الحذف',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احذف',
                    cancelButtonText: 'إلغاء',
                    confirmButtonColor: '',
                });

                if (result.isConfirmed) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            });
        });


        ['edit-paid-amount-input', 'edit-cash-amount-input', 'edit-card-amount-input', 'edit-debt-amount-input'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', () => {
                editSalePaymentTouched = true;
                if (id === 'edit-paid-amount-input') {
                    syncMixedSplitFromPaidAmount();
                } else if (id === 'edit-cash-amount-input' || id === 'edit-card-amount-input') {
                    syncMixedPaidAmountFromSplit();
                } else if (id === 'edit-debt-amount-input') {
                    updateEditSaleFields(false);
                }
            });
        });

        document.getElementById('edit-sale-form')?.addEventListener('submit', async (event) => {
            if (!editSaleHasHigherReplacement || editSalePaymentTouched || editSaleConfirmedHigherReplacement) return;

            event.preventDefault();
            event.stopPropagation();

            const message = 'المنتج البديل سعره أعلى من المنتج السابق ولم تعدل مبلغ الدفع يدوياً. سيتم اعتماد سعر بيع المنتج الجديد وتحديث مبلغ العملية. هل تريد المتابعة؟';
            if (typeof Swal === 'undefined') {
                if (window.confirm(message)) {
                    editSaleConfirmedHigherReplacement = true;
                    event.target.submit();
                }
                return;
            }

            const result = await Swal.fire({
                title: 'تأكيد اعتماد سعر المنتج الجديد',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، اعتمد السعر الجديد',
                cancelButtonText: 'مراجعة المبلغ',
            });

            if (result.isConfirmed) {
                editSaleConfirmedHigherReplacement = true;
                event.target.submit();
            }
        });

        const failedModalId = config.failedModalId;
        if (failedModalId) {
            openEditSaleModal(failedModalId, config.failedOldValues || []);
        }
    });

    // تتاح الأسماء التالية لمفوض عقود data-sensitive-* فقط، بينما يبقى التنفيذ داخل هذه الوحدة.
    Object.assign(window, {
        toggleFinancialDetails,
        toggleDetails,
        markEditSalePaymentTouched,
        updateEditSaleFields,
        syncMixedPaidAmountFromSplit,
        syncEditedOperationTotal,
        enforceEditQuantityLimit,
        renderEditProductSearchResults,
        handleEditUnitTypeChange,
        adjustEditSaleQuantity,
        removeEditSaleItemRow,
        addEditSaleProductRow,
        openFinancialEditModal,
        closeFinancialEditModal,
        openEditSaleModal,
        closeEditSaleModal,
        chooseEditProduct,
    });
}
