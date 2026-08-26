// ينقل تفاعلات عرض واستلام واعتماد الطلبية كما هي؛ الحالة والتغييرات تصل عبر عقد Blade.
const root = document.querySelector('[data-purchase-order-show-config]');
if (root) {
    const config = JSON.parse(root.dataset.purchaseOrderShowConfig || '{}');
    if (config.clearDraft) {
        try { localStorage.removeItem(config.draftKey); } catch (error) { /* نجاح الحفظ لا يعتمد على تخزين المتصفح. */ }
    }
    document.addEventListener('DOMContentLoaded', () => {
        const sendPurchaseOrderForm = document.getElementById('sendPurchaseOrderForm');
        sendPurchaseOrderForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const message = 'سيتم إقفال الطلبية واعتمادها للإرسال للمورد، ولن يمكن تعديل بنودها بعد ذلك. هل تريد المتابعة؟';

            if (typeof Swal === 'undefined') {
                if (window.confirm(message)) event.target.submit();
                return;
            }

            const result = await Swal.fire({
                title: 'تأكيد اعتماد الطلبية',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'نعم، اعتماد وإرسال',
                cancelButtonText: 'إلغاء',
            });

            if (result.isConfirmed) event.target.submit();
        });

        const stockApprovalCostChanges = config.stockApprovalCostChanges || [];
        const receiveItemsSearch = document.getElementById('receiveItemsSearch');
        const receiveItemsSearchCount = document.getElementById('receiveItemsSearchCount');
        const receiveItemsEmpty = document.getElementById('receiveItemsEmpty');
        const receiptFilterButtons = Array.from(document.querySelectorAll('[data-receipt-filter]'));
        const receiptReviewForm = document.getElementById('receipt-review');
        const receiptReviewStoragePrefix = receiptReviewForm?.dataset.orderId
            ? `purchase-order-receipt-review:${receiptReviewForm.dataset.orderId}`
            : null;
        const storedReceiptFilter = receiptReviewStoragePrefix ? sessionStorage.getItem(`${receiptReviewStoragePrefix}:filter`) : null;
        let activeReceiptFilter = storedReceiptFilter && receiptFilterButtons.some((button) => button.dataset.receiptFilter === storedReceiptFilter)
            ? storedReceiptFilter
            : 'all';
        if (receiveItemsSearch && receiptReviewStoragePrefix) {
            receiveItemsSearch.value = sessionStorage.getItem(`${receiptReviewStoragePrefix}:search`) || '';
        }

        function normalizeReceiveSearch(text) {
            return String(text || '').trim().toLowerCase()
                .replace(/[أإآ]/g, 'ا')
                .replace(/ة/g, 'ه')
                .replace(/ى/g, 'ي')
                .replace(/[\u064B-\u065F]/g, '')
                .replace(/\s+/g, ' ');
        }

        const inventorySelection = document.querySelector('[data-inventory-selection]');
        const inventorySearch = inventorySelection?.querySelector('[data-inventory-search]');
        const inventoryOptions = Array.from(inventorySelection?.querySelectorAll('[data-inventory-option]') || []);
        const inventorySelectionCount = inventorySelection?.querySelector('[data-inventory-selection-count]');
        const inventoryEmpty = inventorySelection?.querySelector('[data-inventory-empty]');
        const inventoryShowAll = inventorySelection?.querySelector('[data-inventory-show-all]');
        const inventoryShowAllLabel = inventorySelection?.querySelector('[data-inventory-show-all-label]');
        let inventoryExpanded = false;

        const updateInventorySelection = () => {
            if (!inventorySelection) return;
            const term = normalizeReceiveSearch(inventorySearch?.value || '');
            const termWithoutAlef = term.replace(/ا/g, '');
            let visible = 0;
            let selected = 0;

            inventoryOptions.forEach((option) => {
                const text = normalizeReceiveSearch(option.dataset.search || option.textContent || '');
                const textWithoutAlef = text.replace(/ا/g, '');
                const matched = term
                    ? text.includes(term) || textWithoutAlef.includes(termWithoutAlef)
                    : inventoryExpanded;
                option.classList.toggle('hidden', !matched);
                if (matched) visible += 1;
                if (option.querySelector('input[type="checkbox"]')?.checked) selected += 1;
            });

            inventoryEmpty?.classList.toggle('hidden', visible > 0);
            if (inventoryEmpty && visible === 0) {
                inventoryEmpty.textContent = term
                    ? 'لا توجد منتجات مطابقة للبحث.'
                    : 'ابدأ بكتابة اسم المنتج أو اضغط إظهار كل المنتجات.';
            }
            inventoryShowAll?.setAttribute('aria-expanded', String(inventoryExpanded));
            if (inventoryShowAllLabel) inventoryShowAllLabel.textContent = inventoryExpanded ? 'إخفاء المنتجات' : 'إظهار كل المنتجات';
            if (inventorySelectionCount) {
                inventorySelectionCount.textContent = `تم تحديد ${selected} من ${inventoryOptions.length} منتج${visible ? `، والمعروض الآن ${visible}` : ''}.`;
            }
        };

        inventorySearch?.addEventListener('input', () => {
            inventoryExpanded = false;
            updateInventorySelection();
        });
        inventoryOptions.forEach((option) => option.querySelector('input[type="checkbox"]')?.addEventListener('change', updateInventorySelection));
        inventoryShowAll?.addEventListener('click', () => {
            if (inventorySearch) inventorySearch.value = '';
            inventoryExpanded = !inventoryExpanded;
            updateInventorySelection();
            if (!inventoryExpanded) inventorySearch?.focus();
        });
        inventorySelection?.querySelector('[data-inventory-select-all]')?.addEventListener('click', () => {
            inventoryOptions.forEach((option) => {
                option.querySelector('input[type="checkbox"]').checked = true;
            });
            updateInventorySelection();
        });
        inventorySelection?.querySelector('[data-inventory-clear]')?.addEventListener('click', () => {
            inventoryOptions.forEach((option) => {
                option.querySelector('input[type="checkbox"]').checked = false;
            });
            updateInventorySelection();
        });
        updateInventorySelection();

        const ownerCountReview = document.querySelector('[data-owner-count-review]');
        const ownerCountCheckboxes = Array.from(ownerCountReview?.querySelectorAll('[data-owner-count-checkbox]') || []);
        ownerCountReview?.querySelector('[data-owner-count-select-all]')?.addEventListener('click', () => {
            ownerCountCheckboxes.forEach((checkbox) => { checkbox.checked = true; });
        });
        ownerCountReview?.querySelector('[data-owner-count-clear]')?.addEventListener('click', () => {
            ownerCountCheckboxes.forEach((checkbox) => { checkbox.checked = false; });
        });

        function updateReceiveItemsSearch() {
            if (!receiveItemsSearch) return;
            const term = normalizeReceiveSearch(receiveItemsSearch.value || '');
            const termWithoutAlef = term.replace(/ا/g, '');
            const cards = Array.from(document.querySelectorAll('.js-receive-item'));
            let visible = 0;

            cards.forEach((card) => {
                const text = normalizeReceiveSearch(card.dataset.search || card.textContent || '');
                const textWithoutAlef = text.replace(/ا/g, '');
                const matchesNormalizedArabic = Boolean(termWithoutAlef) && textWithoutAlef.includes(termWithoutAlef);
                const matchesSearch = !term || text.includes(term) || matchesNormalizedArabic;
                const matchesFilter = activeReceiptFilter === 'all' || card.dataset[activeReceiptFilter] === '1';
                const matched = matchesSearch && matchesFilter;
                card.classList.toggle('hidden', !matched);
                if (matched) visible += 1;
            });

            if (receiveItemsSearchCount) {
                receiveItemsSearchCount.textContent = cards.length
                    ? `المعروض ${visible} من ${cards.length} بند استلام.`
                    : 'لا توجد بنود استلام.';
            }
            receiveItemsEmpty?.classList.toggle('hidden', visible > 0 || cards.length === 0);
        }

        receiveItemsSearch?.addEventListener('input', () => {
            if (receiptReviewStoragePrefix) sessionStorage.setItem(`${receiptReviewStoragePrefix}:search`, receiveItemsSearch.value);
            updateReceiveItemsSearch();
        });
        receiptFilterButtons.forEach((button) => button.addEventListener('click', () => {
            activeReceiptFilter = button.dataset.receiptFilter || 'all';
            if (receiptReviewStoragePrefix) sessionStorage.setItem(`${receiptReviewStoragePrefix}:filter`, activeReceiptFilter);
            receiptFilterButtons.forEach((candidate) => {
                const active = candidate === button;
                candidate.setAttribute('aria-pressed', String(active));
                candidate.classList.toggle('ui-btn-primary', active);
                candidate.classList.toggle('ui-btn-secondary', !active);
            });
            updateReceiveItemsSearch();
        }));
        receiptFilterButtons.forEach((button) => {
            const active = button.dataset.receiptFilter === activeReceiptFilter;
            button.setAttribute('aria-pressed', String(active));
            button.classList.toggle('ui-btn-primary', active);
            button.classList.toggle('ui-btn-secondary', !active);
        });
        updateReceiveItemsSearch();

        const summaryItemsSearch = document.getElementById('summaryItemsSearch');
        const summaryItemsSearchCount = document.getElementById('summaryItemsSearchCount');

        function updateSummaryItemsSearch() {
            if (!summaryItemsSearch) return;
            const term = normalizeReceiveSearch(summaryItemsSearch.value || '');
            const termWithoutAlef = term.replace(/ا/g, '');
            const cards = Array.from(document.querySelectorAll('.js-summary-item'));
            let visible = 0;

            cards.forEach((card) => {
                const text = normalizeReceiveSearch(card.dataset.search || card.textContent || '');
                const textWithoutAlef = text.replace(/ا/g, '');
                const matched = !term || text.includes(term) || textWithoutAlef.includes(termWithoutAlef);
                card.classList.toggle('hidden', !matched);
                if (matched) visible += 1;
            });

            if (summaryItemsSearchCount) {
                summaryItemsSearchCount.textContent = cards.length
                    ? `المعروض ${visible} من ${cards.length} بند.`
                    : 'لا توجد بنود في الطلبية.';
            }
        }

        summaryItemsSearch?.addEventListener('input', updateSummaryItemsSearch);
        updateSummaryItemsSearch();

        // Dropdown Logic
        document.querySelectorAll('.js-dropdown-toggle').forEach((toggle) => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const container = toggle.closest('.js-dropdown-container');
                const menu = container.querySelector('.js-dropdown-menu');
                const filterInput = menu.querySelector('.js-match-filter');
                const optionsList = menu.querySelector('.js-options-list');

                document.querySelectorAll('.js-dropdown-menu').forEach((m) => {
                    if (m !== menu) m.classList.add('hidden');
                });
                document.querySelectorAll('.js-dropdown-toggle svg').forEach((svg) => {
                    if (svg !== toggle.querySelector('svg')) svg.classList.remove('rotate-180');
                });

                menu.classList.toggle('hidden');
                toggle.querySelector('svg').classList.toggle('rotate-180');

                if (!menu.classList.contains('hidden')) {
                    if (filterInput) {
                        filterInput.value = '';
                        filterInput.focus();
                    }
                    optionsList.querySelectorAll('.js-option-item').forEach((item) => {
                        item.classList.toggle('hidden', item.dataset.value !== '');
                    });
                }
            });
        });

        // Dropdown Search Logic
        document.querySelectorAll('.js-match-filter').forEach((input) => {
            input.addEventListener('input', () => {
                const term = normalizeReceiveSearch(input.value);
                const list = input.parentElement.querySelector('.js-options-list');

                list.querySelectorAll('.js-option-item').forEach((item) => {
                    if (!item.dataset.value) {
                        item.classList.remove('hidden'); // Always show 'cancel match'
                        return;
                    }
                    const optionText = normalizeReceiveSearch(item.dataset.search || item.textContent);
                    item.classList.toggle('hidden', !term || !optionText.includes(term));
                });
            });
        });

        // Dropdown Selection Logic
        document.querySelectorAll('.js-option-item').forEach((item) => {
            item.addEventListener('click', () => {
                const container = item.closest('.js-dropdown-container');
                const toggle = container.querySelector('.js-dropdown-toggle');
                const hiddenInput = container.querySelector('.js-hidden-input');
                const label = toggle.querySelector('.js-selected-label');

                hiddenInput.value = item.dataset.value || '';
                label.textContent = item.textContent.trim();

                // Highlight selected
                container.querySelectorAll('.js-option-item').forEach(opt => opt.classList.remove('ui-status-info-bg', 'ui-title', 'font-bold', 'border', 'ui-border'));
                if(item.dataset.value) item.classList.add('ui-status-info-bg', 'ui-title', 'font-bold', 'border', 'ui-border');

                container.querySelector('.js-dropdown-menu').classList.add('hidden');
                toggle.querySelector('svg').classList.remove('rotate-180');

                const rowInput = container.closest('.js-receive-item').querySelector('.js-receipt-price');
                if(rowInput) rowInput.dispatchEvent(new Event('input'));
            });
        });

        // Close dropdowns on outside click
        document.addEventListener('click', () => {
            document.querySelectorAll('.js-dropdown-menu').forEach((m) => m.classList.add('hidden'));
            document.querySelectorAll('.js-dropdown-toggle svg').forEach((s) => s.classList.remove('rotate-180'));
        });

        const ownerProductModal = document.getElementById('ownerProductModal');
        const ownerProductForm = document.getElementById('ownerProductForm');
        const ownerProductName = document.getElementById('ownerProductName');
        const ownerProductUnit = document.getElementById('ownerProductUnit');
        const ownerProductErrors = document.getElementById('ownerProductErrors');
        const ownerProductCategory = document.getElementById('ownerProductCategory');
        const ownerProductSaleCategoryField = document.getElementById('ownerProductSaleCategoryField');
        const ownerProductOwnerCategory = document.getElementById('ownerProductOwnerCategory');
        const ownerProductKitFields = document.getElementById('ownerProductKitFields');
        const ownerProductRollFields = document.getElementById('ownerProductRollFields');
        const ownerProductUnitLabel = document.getElementById('ownerProductUnitLabel');
        const ownerProductUnitCost = document.getElementById('ownerProductUnitCost');
        const ownerProductRollLength = document.getElementById('ownerProductRollLength');
        const ownerProductItemsPerUnitInput = document.getElementById('ownerProductItemsPerUnitInput');
        const ownerProductRollLengthInput = document.getElementById('ownerProductRollLengthInput');
        const ownerProductReceiptTotalCost = document.getElementById('ownerProductReceiptTotalCost');
        const ownerProductReceivedQuantity = document.getElementById('ownerProductReceivedQuantity');
        const ownerProductSellingPriceField = document.getElementById('ownerProductSellingPriceField');
        const ownerProductSellingPrice = document.getElementById('ownerProductSellingPrice');
        const ownerProductSaleFields = document.getElementById('ownerProductSaleFields');
        const ownerExistingProduct = document.getElementById('ownerExistingProduct');
        const ownerExistingProductSearch = document.getElementById('ownerExistingProductSearch');
        const ownerProductLinkFields = document.getElementById('ownerProductLinkFields');
        const ownerProductCreateFields = document.getElementById('ownerProductCreateFields');
        const ownerProductUnitChoice = document.getElementById('ownerProductUnitChoice');
        const ownerProductFractions = document.getElementById('ownerProductFractions');
        const ownerProductFractionsList = document.getElementById('ownerProductFractionsList');
        const addOwnerProductFraction = document.getElementById('addOwnerProductFraction');
        const ownerProductSubmitText = document.getElementById('ownerProductSubmitText');
        const ownerProductSellingPriceLabel = document.getElementById('ownerProductSellingPriceLabel');
        const ownerProductPiecePriceField = document.getElementById('ownerProductPiecePriceField');
        const ownerProductCartonField = document.getElementById('ownerProductCartonField');
        const ownerProductWasteField = document.getElementById('ownerProductWasteField');
        const ownerProductQuickSaleField = document.getElementById('ownerProductQuickSaleField');

        const isCreatingOwnerProduct = () => ownerProductForm?.querySelector('input[name="product_action"]:checked')?.value === 'create';

        const toggleOwnerProductUsage = () => {
            const creating = isCreatingOwnerProduct();
            const isSale = creating && ownerProductForm?.querySelector('input[name="usage_type"]:checked')?.value === 'sale';
            const unit = ownerProductUnit?.value || 'piece';
            const ownerCategoryId = ownerProductCategory?.dataset.ownerCategoryId || '';
            ownerProductSellingPriceField?.classList.toggle('hidden', !creating);
            ownerProductSaleFields?.classList.toggle('hidden', !creating);
            ownerProductSellingPrice?.toggleAttribute('required', isSale);
            ownerProductPiecePriceField?.classList.toggle('hidden', !creating || unit !== 'kit');
            ownerProductCartonField?.classList.toggle('hidden', !creating || unit === 'roll');
            ownerProductWasteField?.classList.toggle('hidden', !creating || unit !== 'roll');
            ownerProductQuickSaleField?.classList.toggle('hidden', !creating || unit !== 'kit');
            if (ownerProductSellingPriceLabel) {
                ownerProductSellingPriceLabel.textContent = ({ piece: 'سعر بيع الحبة', kit: 'سعر بيع الطقم الكامل', roll: 'سعر بيع الرول الكامل' })[unit] || 'سعر البيع';
            }
            const canUseOwnerCategory = !isSale && Boolean(ownerCategoryId);
            ownerProductSaleCategoryField?.classList.toggle('hidden', canUseOwnerCategory);
            ownerProductOwnerCategory?.classList.toggle('hidden', !canUseOwnerCategory);
            if (canUseOwnerCategory && ownerProductCategory) ownerProductCategory.value = ownerCategoryId;
            if (isSale && ownerProductCategory?.value === ownerCategoryId) ownerProductCategory.value = '';
            ownerProductCategory?.toggleAttribute('required', isCreatingOwnerProduct() && !canUseOwnerCategory);
        };

        const toggleExistingProductLink = () => {
            const creating = isCreatingOwnerProduct();
            ownerProductLinkFields?.classList.toggle('hidden', creating);
            ownerProductCreateFields?.classList.toggle('hidden', !creating);
            ownerExistingProduct?.toggleAttribute('required', !creating);
            ownerProductName?.toggleAttribute('required', creating);
            if (ownerProductSubmitText) ownerProductSubmitText.textContent = creating ? 'إنشاء المنتج وربطه بالطلبية' : 'ربط المنتج بالطلبية';
            toggleOwnerProductUnitFields();
            toggleOwnerProductUsage();
        };

        const toggleOwnerProductUnitFields = () => {
            const unit = ownerProductUnit?.value || 'piece';
            ownerProductKitFields?.classList.toggle('hidden', unit !== 'kit');
            ownerProductRollFields?.classList.toggle('hidden', unit !== 'roll');
            ownerProductFractions?.classList.toggle('hidden', unit !== 'roll');
            ownerProductItemsPerUnitInput?.toggleAttribute('required', isCreatingOwnerProduct() && unit === 'kit');
            ownerProductRollLengthInput?.toggleAttribute('required', isCreatingOwnerProduct() && unit === 'roll');
            if (ownerProductUnitLabel) ownerProductUnitLabel.textContent = ({ piece: 'حبة', kit: 'طقم', roll: 'رول' })[unit] || 'حبة';
        };

        const closeOwnerProductModal = () => {
            ownerProductModal?.classList.add('hidden');
            ownerProductErrors?.classList.add('hidden');
        };

        document.querySelectorAll('.js-open-owner-product-modal').forEach((button) => {
            button.addEventListener('click', () => {
                ownerProductForm?.reset();
                if (ownerProductFractionsList) ownerProductFractionsList.replaceChildren();
                ownerProductFractionIndex = 0;
                ownerProductForm?.setAttribute('action', button.dataset.ownerProductUrl || '');
                if (ownerProductName) ownerProductName.value = button.dataset.ownerProductName || '';
                if (ownerProductUnit) ownerProductUnit.value = button.dataset.ownerProductUnit || 'piece';
                if (ownerProductUnitChoice) ownerProductUnitChoice.value = ownerProductUnit?.value || 'piece';
                const card = button.closest('.js-receive-item');
                const enteredQuantity = Number(card?.querySelector('input[name$="[quantity_received]"]')?.value || button.dataset.ownerRequestedQuantity || 0);
                const enteredTotalCost = Number(card?.querySelector('.js-receipt-price')?.value || button.dataset.ownerOrderCost || 0);
                const unitCost = enteredQuantity > 0 ? enteredTotalCost / enteredQuantity : 0;
                const itemsPerUnit = Number(button.dataset.ownerItemsPerUnit || 0);
                const rollLength = Number(button.dataset.ownerRollLength || 0);
                if (ownerProductReceiptTotalCost) ownerProductReceiptTotalCost.value = enteredTotalCost;
                if (ownerProductReceivedQuantity) ownerProductReceivedQuantity.value = enteredQuantity;
                if (ownerProductUnitCost) ownerProductUnitCost.textContent = `${unitCost.toFixed(2)} ر.س`;
                if (ownerProductItemsPerUnitInput) {
                    ownerProductItemsPerUnitInput.value = itemsPerUnit || '';
                }
                if (ownerProductRollLength) {
                    ownerProductRollLength.textContent = rollLength > 0 ? `${rollLength} متر` : '-';
                    ownerProductRollLength.classList.toggle('hidden', rollLength <= 0);
                }
                if (ownerProductRollLengthInput) {
                    ownerProductRollLengthInput.value = rollLength || '';
                    ownerProductRollLengthInput.classList.remove('hidden');
                }
                ownerProductErrors?.classList.add('hidden');
                toggleOwnerProductUnitFields();
                toggleOwnerProductUsage();
                toggleExistingProductLink();
                ownerProductModal?.classList.remove('hidden');
            });
        });

        document.getElementById('closeOwnerProductModal')?.addEventListener('click', closeOwnerProductModal);
        document.getElementById('cancelOwnerProduct')?.addEventListener('click', closeOwnerProductModal);

        ownerProductForm?.querySelectorAll('input[name="usage_type"]').forEach((input) => input.addEventListener('change', toggleOwnerProductUsage));
        ownerProductForm?.querySelectorAll('input[name="product_action"]').forEach((input) => input.addEventListener('change', toggleExistingProductLink));
        ownerProductUnitChoice?.addEventListener('change', () => {
            if (ownerProductUnit) ownerProductUnit.value = ownerProductUnitChoice.value;
            toggleOwnerProductUnitFields();
            toggleOwnerProductUsage();
        });

        let ownerProductFractionIndex = 0;
        const addFractionRow = () => {
            if (!ownerProductFractionsList) return;
            const index = ownerProductFractionIndex++;
            const row = document.createElement('div');
            row.className = 'ui-card grid gap-3 p-3 sm:grid-cols-4';
            row.innerHTML = `
                <label class="sm:col-span-2"><span class="ui-text-soft block mb-1">اسم خيار البيع</span><input class="ui-input" name="fractions[${index}][option_label]" placeholder="مثال: متر أو نصف متر"></label>
                <label><span class="ui-text-soft block mb-1">الخصم بالمتر</span><input class="ui-input" type="number" min="0.01" step="0.01" name="fractions[${index}][deduction_value]"></label>
                <label><span class="ui-text-soft block mb-1">سعر البيع</span><input class="ui-input" type="number" min="0" step="0.01" name="fractions[${index}][price]"></label>
                <button type="button" class="ui-btn ui-btn-danger sm:col-span-4">حذف الخيار</button>`;
            row.querySelector('button')?.addEventListener('click', () => row.remove());
            ownerProductFractionsList.appendChild(row);
        };
        addOwnerProductFraction?.addEventListener('click', addFractionRow);

        ownerExistingProduct?.addEventListener('change', toggleExistingProductLink);
        ownerExistingProductSearch?.addEventListener('input', () => {
            const term = normalizeReceiveSearch(ownerExistingProductSearch.value);
            Array.from(ownerExistingProduct?.options || []).forEach((option) => {
                if (!option.value) return;
                // بيانات البحث تضم اسم المنتج ووصفه، بينما يبقى اسم المنتج وحده ظاهرًا في القائمة.
                const searchableProduct = normalizeReceiveSearch(option.dataset.search || option.textContent);
                const termWithoutAlef = term.replace(/ا/g, '');
                const searchableWithoutAlef = searchableProduct.replace(/ا/g, '');
                const matchesWithoutAlef = Boolean(termWithoutAlef) && searchableWithoutAlef.includes(termWithoutAlef);
                option.hidden = Boolean(term)
                    && !searchableProduct.includes(term)
                    && !matchesWithoutAlef;
            });
        });

        ownerProductForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitButton = ownerProductForm.querySelector('button[type="submit"]');
            submitButton?.setAttribute('disabled', 'disabled');
            ownerProductErrors?.classList.add('hidden');

            try {
                const response = await fetch(ownerProductForm.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(ownerProductForm),
                });
                const data = await response.json();

                if (!response.ok) {
                    const errors = data.errors ? Object.values(data.errors).flat() : [data.message || 'تعذر حفظ المنتج.'];
                    if (ownerProductErrors) {
                        ownerProductErrors.textContent = errors.join(' ');
                        ownerProductErrors.classList.remove('hidden');
                    }
                    return;
                }

                closeOwnerProductModal();
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({ icon: 'success', title: 'تم الحفظ', text: data.message, confirmButtonText: 'حسنًا' });
                }
                window.location.reload();
            } catch (error) {
                if (ownerProductErrors) {
                    ownerProductErrors.textContent = 'حدث خطأ في الاتصال بالخادم، يرجى المحاولة مرة أخرى.';
                    ownerProductErrors.classList.remove('hidden');
                }
            } finally {
                submitButton?.removeAttribute('disabled');
            }
        });

        toggleOwnerProductUnitFields();
        toggleOwnerProductUsage();
        toggleExistingProductLink();

        // Variance Calculation Logic
        document.querySelectorAll('.js-receipt-price').forEach((input) => {
            const updateVariance = () => {
                const orderPrice = parseFloat(input.dataset.orderPrice || '0');
                const requested = parseFloat(input.dataset.requestedQty || '0');

                const card = input.closest('.js-receive-item');
                const receivedInput = card?.querySelector('input[name$="[quantity_received]"]');

                // Safe parsing for received quantity
                let received = requested;
                if (receivedInput && receivedInput.value !== '') {
                    received = parseFloat(receivedInput.value);
                }
                if (isNaN(received)) received = 0;

                const unitElement = card?.querySelector('.js-receipt-unit');
                const selectedUnit = unitElement?.tagName === 'SELECT' ? unitElement.querySelector('option:checked') : unitElement;
                const unitCost = parseFloat(selectedUnit?.dataset.unitCost || '0');

                // Calculate Expected
                let expected = 0;
                if (unitCost > 0) {
                    expected = unitCost * received;
                } else {
                    const avgPrice = requested > 0 ? orderPrice / requested : orderPrice;
                    expected = avgPrice * received;
                }
                const effectiveUnitCost = unitCost > 0 ? unitCost : (received > 0 ? expected / received : 0);

                const expectedTarget = card?.querySelector('.js-receipt-expected');
                if (expectedTarget) {
                    const unitLabel = selectedUnit?.textContent?.split('—')[0]?.trim() || '';
                    expectedTarget.textContent = `المتوقع لـ ${received.toFixed(2)} ${unitLabel}: ${expected.toFixed(2)} ر.س (سعر ${unitLabel} الواحدة ${effectiveUnitCost.toFixed(2)} ر.س)`;
                }

                if (input.dataset.autoFill === '1') {
                    input.value = expected.toFixed(2);
                }

                // Calculate actual receipt value
                const receipt = input.value === '' ? expected : parseFloat(input.value || '0');
                const target = document.getElementById(input.dataset.varianceTarget);

                const variance = receipt > 0 ? receipt - expected : 0;
                const looksLikeSingleUnitPrice = received > 1 && effectiveUnitCost > 0 && Math.abs(receipt - effectiveUnitCost) <= 0.01;
                const alternateUnit = Array.from(unitElement?.options || []).find((option) => option !== selectedUnit
                    && Math.abs(receipt - (parseFloat(option.dataset.unitCost || '0') * received)) <= 0.01);

                if (target) {
                    target.className = 'ui-text-caption font-bold px-3 py-1.5 rounded-lg whitespace-nowrap transition-colors duration-300';
                    if (looksLikeSingleUnitPrice) {
                        target.classList.remove('hidden');
                        target.textContent = `تنبيه: الكمية ${received.toFixed(2)} لكن السعر المدخل يساوي سعر وحدة واحدة فقط.`;
                        target.classList.add('ui-status-warning-bg', 'ui-status-warning', 'border', 'ui-border');
                    } else if (alternateUnit) {
                        const alternateLabel = alternateUnit.textContent?.split('—')[0]?.trim() || 'وحدة أخرى';
                        target.classList.remove('hidden');
                        target.textContent = `تحقق من الوحدة: السعر المدخل أقرب إلى حساب ${alternateLabel}.`;
                        target.classList.add('ui-status-warning-bg', 'ui-status-warning', 'border', 'ui-border');
                    } else if (variance > 0.01) {
                        target.classList.remove('hidden');
                        target.textContent = 'زيادة: ' + variance.toFixed(2) + ' ر.س';
                        target.classList.add('ui-status-danger-bg', 'ui-status-danger', 'border', 'ui-border');
                        if (card) {
                            card.classList.remove('ui-border', 'ui-border');
                            card.classList.add('ui-border');
                        }
                    } else if (variance < -0.01) {
                        target.classList.remove('hidden');
                        target.textContent = 'نقصان: ' + Math.abs(variance).toFixed(2) + ' ر.س';
                        target.classList.add('ui-status-success-bg', 'ui-status-success', 'border', 'ui-border');
                        if (card) {
                            card.classList.remove('ui-border', 'ui-border');
                            card.classList.add('ui-border');
                        }
                    } else {
                        target.textContent = '';
                        target.classList.add('hidden');
                        if (card) {
                            card.classList.remove('ui-border', 'ui-border');
                            card.classList.add('ui-border');
                        }
                    }
                }

                // Auto-check update cost if variance is positive (increase in cost)
                const costCheckbox = card?.querySelector('input[name$="[update_product_cost]"][type="checkbox"]');
                if (costCheckbox && variance > 0.01 && !costCheckbox.dataset.userToggled) {
                    costCheckbox.checked = true;
                }
            };

            input.addEventListener('input', () => {
                input.dataset.autoFill = '0';
                updateVariance();
            });
            const card = input.closest('.js-receive-item');
            card?.querySelector('input[name$="[quantity_received]"]')?.addEventListener('input', updateVariance);
            card?.querySelector('.js-receipt-unit')?.addEventListener('change', updateVariance);

            // Track manual toggle of the checkbox
            const costCheckbox = card?.querySelector('input[name$="[update_product_cost]"][type="checkbox"]');
            if(costCheckbox) {
                costCheckbox.addEventListener('change', () => {
                    costCheckbox.dataset.userToggled = 'true';
                });
            }
            updateVariance();
        });

        const approveOrderForm = document.getElementById('approveOrderForm');
        const approveStorageKey = approveOrderForm ? `purchase-order-approval-pending-${approveOrderForm.dataset.orderId}` : null;

        if (approveStorageKey && config.orderStatus === 'approved') {
            localStorage.removeItem(approveStorageKey);
        }

        function lockApproveForm(form) {
            form.dataset.submitting = '1';
            localStorage.setItem(`purchase-order-approval-pending-${form.dataset.orderId}`, '1');
            const button = form.querySelector('#approveOrderButton');
            const label = form.querySelector('.js-approve-button-text');
            if (button) button.disabled = true;
            if (label) label.textContent = 'جار الاعتماد... لا تغلق الصفحة';
        }

        // Final Approval Warning + repeat-click protection
        approveOrderForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target;
            if (form.dataset.submitting === '1') {
                return;
            }

            if (typeof Swal === 'undefined') {
                if (window.confirm('تأكد من البيانات والكميات المدخلة. لا يمكن التراجع عن هذه الخطوة.')) {
                    lockApproveForm(form);
                    form.submit();
                }
                return;
            }

            let costChangesHtml = '';
            if (stockApprovalCostChanges.length) {
                costChangesHtml = `
                    <div class="mt-4 text-right ui-surface-muted-bg rounded-xl p-3 border ui-border">
                        <strong class="ui-status-info text-sm block mb-2"><svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> سيتم تحديث تكاليف المنتجات التالية:</strong>
                        <div class="max-h-40 overflow-y-auto custom-scrollbar space-y-2">
                            ${stockApprovalCostChanges.map(item => `
                                <div class="flex justify-between items-center ui-text-caption ui-surface-muted-bg p-2 rounded-lg">
                                    <span class="ui-text-muted truncate pl-2 max-w-[50%]">${item.name}</span>
                                    <div class="flex items-center gap-2 ltr" dir="ltr">
                                        <span class="ui-text-muted line-through">${Number(item.current_cost).toFixed(2)}</span>
                                        <svg class="w-3 h-3 ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                        <span class="ui-status-danger font-bold">${Number(item.new_cost).toFixed(2)}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>`;
            } else {
                costChangesHtml = '<div class="mt-4 text-sm ui-text-muted ui-surface-muted-bg p-3 rounded-lg border ui-border">لا توجد منتجات محددة لتحديث تكلفتها في النظام.</div>';
            }

            const result = await Swal.fire({
                title: 'تأكيد الاعتماد المخزني',
                html: `<div class="ui-text-soft text-sm">تأكد من البيانات والكميات المدخلة. لا يمكن التراجع عن هذه الخطوة.</div> ${costChangesHtml}`,
                icon: 'warning',
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: 'تأكيد الاعتماد',
                cancelButtonText: 'رجوع للمراجعة',
                customClass: {
                    popup: 'ui-swal-popup',
                    title: 'ui-swal-title',
                    htmlContainer: 'ui-swal-content',
                    actions: 'ui-swal-actions',
                    confirmButton: 'ui-btn ui-btn-primary ui-btn-borderless',
                    cancelButton: 'ui-btn ui-btn-secondary'
                }
            });

            if (result.isConfirmed) {
                lockApproveForm(form);
                form.submit();
            }
        });
    });
}
