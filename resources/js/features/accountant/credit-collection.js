// تستخرج هذه الوحدة سلوك تحصيل الآجل كما هو؛ القيم والمسارات تأتي من عقد Blade الآمن أدناه.
const root = document.querySelector('[data-credit-collection-config]');

if (root) {
    const config = JSON.parse(root.dataset.creditCollectionConfig || '{}');
    const allSales = config.sales || {};

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function openCollectionModal(empId, empName) {
        document.getElementById('empName').innerText = empName;

        if (empId == config.accountantEmployeeId) {
            document.getElementById('creditSalesList').innerHTML = `
                <div class="text-center py-6">
                    <div class="w-12 h-12 mx-auto mb-2 rounded-full ui-status-danger-bg border ui-status-danger-border flex items-center justify-center">
                        <svg class="w-6 h-6 ui-status-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </div>
                    <p class="ui-text-soft ui-text-caption">عفوًا لا تملك الإذن بذلك، راجع مالك المتجر أو المدير</p>
                </div>
            `;
            document.getElementById('collectionModal').classList.remove('hidden');
            return;
        }

        const employeeSales = allSales[empId] || [];

        if (!employeeSales.length) {
            document.getElementById('creditSalesList').innerHTML = `
                <div class="text-center py-6 ui-text-soft ui-text-caption">لا توجد عمليات آجل معلقة</div>
            `;
            document.getElementById('collectionModal').classList.remove('hidden');
            return;
        }

        const html = employeeSales.map((sale) => {
            const fullRoute = config.collectionRouteTemplate.replace('SALE', sale.id);
            const remainingNumber = Number(sale.remaining_amount || 0);
            const remainingAmount = remainingNumber.toFixed(2);
            const saleAmount = Number(sale.amount || 0).toFixed(2);
            const creditNote = sale.credit_note ? String(sale.credit_note) : '';
            const hasCollections = Array.isArray(sale.collection_payments) && sale.collection_payments.length > 0;
            const amountLabel = hasCollections ? 'المتبقي' : 'المبلغ';
            const shownAmount = hasCollections ? remainingAmount : saleAmount;

            return `
                <div class="ui-card-muted rounded-lg p-2.5">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <div class="min-w-0">
                            <div class="ui-title ui-text-body font-bold break-words">${escapeHtml(creditNote || sale.description || 'أجل بدون ملاحظة')}</div>
                            <div class="ui-text-muted ui-text-caption mt-0.5">${escapeHtml(sale.date || '-')}</div>
                        </div>
                        ${hasCollections ? `<span class="ui-status-warning ui-text-caption font-bold shrink-0">تحصيل جزئي</span>` : ''}
                    </div>
                    <div class="ui-text-soft ui-text-meta mb-2">${amountLabel}: <span class="ui-status-warning font-bold">${shownAmount} ريال</span></div>
                    <div class="flex gap-1.5">
                        <!-- تُقرأ بيانات العملية من الوحدة المركزية بدل إنشاء onclick داخل القالب الديناميكي. -->
                        <button type="button" data-sensitive-action="collection.preview" data-sale="${escapeHtml(JSON.stringify(sale))}"
                                class="flex-1 ui-btn ui-btn-info text-sm py-2 rounded-lg">
                            معاينة
                        </button>
                        <button type="button" data-sensitive-action="collection.full" data-route="${fullRoute}" data-amount="${remainingAmount}"
                                class="flex-1 ui-btn ui-btn-success text-sm py-2 rounded-lg disabled:opacity-60 disabled:cursor-not-allowed">
                            كامل
                        </button>
                        <button type="button" data-sensitive-action="collection.partial-open" data-sale-id="${sale.id}" data-amount="${remainingAmount}"
                                class="flex-1 ui-btn ui-btn-warning text-sm py-2 rounded-lg">
                            جزئي
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        document.getElementById('creditSalesList').innerHTML = html;
        document.getElementById('collectionModal').classList.remove('hidden');
    }

    function money(value) {
        return `${Number(value || 0).toFixed(2)} ريال`;
    }

    function paymentStatus(sale) {
        const remaining = Number(sale.remaining_amount || 0);
        const amount = Number(sale.amount || 0);
        if (remaining <= 0) return '<span class="ui-status-success-bg border ui-status-success-border rounded-full px-2 py-0.5 ui-text-caption">تم تحصيل كامل</span>';
        if (remaining < amount) return '<span class="ui-status-info-bg border ui-status-info-border rounded-full px-2 py-0.5 ui-text-caption">تم تحصيل جزئي</span>';
        return '<span class="ui-status-warning-bg border ui-status-warning-border rounded-full px-2 py-0.5 ui-text-caption">غير محصل</span>';
    }

    function renderSaleItemUnitLabel(item) {
        if (item.is_labor || item.unit_type === 'service') return 'خدمة';
        if (item.unit_type === 'piece') return 'حبة';
        if (item.is_splittable) return 'طقم';
        if (item.is_custom) return 'متر';
        return 'وحدة';
    }

    function renderSaleItems(items, laborTotal = 0, laborDescription = '') {
        const displayItems = Array.isArray(items) ? [...items] : [];
        if (Number(laborTotal || 0) > 0) {
            displayItems.push({
                name: laborDescription || 'شغل يد / خدمة',
                quantity: 1,
                unit_type: 'service',
                price: laborTotal,
                total: laborTotal,
                is_labor: true,
            });
        }

        return displayItems.map(item => `
            <div class="grid grid-cols-[1fr_auto] gap-2 border-b ui-border py-1.5 last:border-0">
                <div>
                    <div class="ui-title ui-text-caption font-bold">${escapeHtml(item.name || 'منتج غير محدد')}</div>
                    <div class="ui-text-muted ui-text-caption">${Number(item.quantity || 0)} ${renderSaleItemUnitLabel(item)}</div>
                </div>
                <div class="text-left min-w-[88px]">
                    <div class="ui-text-muted ui-text-caption">سعر البيع</div>
                    <div class="ui-text-soft ui-text-caption">${money(item.price)}</div>
                    <div class="ui-status-success ui-text-caption font-bold">${money(item.total)}</div>
                </div>
            </div>
        `).join('');
    }

    function renderPayments(payments) {
        if (!payments || !payments.length) {
            return '<p class="ui-text-muted ui-text-caption">لا توجد تحصيلات محفوظة بعد.</p>';
        }

        // تاريخ التحصيل مستقل عن تاريخ العملية الأصلية ويظهر داخل كل حركة محفوظة.
        return payments.map(payment => `
            <div class="flex items-center justify-between border-b ui-border py-1.5 last:border-0">
                <div class="ui-text-soft ui-text-caption">${escapeHtml(payment.description || 'تحصيل آجل')}<div class="ui-text-muted ui-text-caption">تاريخ التحصيل: ${escapeHtml(payment.date || '-')} - ${escapeHtml(payment.added_by_name || 'غير محدد')} - ${escapeHtml(payment.payment_method_label || 'كاش')}</div></div>
                <div class="text-left"><div class="ui-status-success font-bold ui-text-caption">${money(payment.amount)}</div><div class="ui-text-muted ui-text-caption">كاش ${money(payment.cash_amount || 0)} / شبكة ${money(payment.card_amount || 0)}</div></div>
            </div>
        `).join('');
    }

    function renderFinancialCards(cards) {
        return cards
            .filter(card => Math.abs(Number(card.value || 0)) > 0.009)
            .map(card => `
                <div class="ui-card-muted p-2">
                    <div class="ui-text-muted">${card.label}</div>
                    <div class="${card.className} font-bold">${money(card.value)}</div>
                </div>
            `).join('');
    }

    function openPreviewModal(sale) {
        document.getElementById('collectionModal')?.classList.add('ui-modal-suspended');
        const linked = sale.linked_sale || null;
        const mixedTotal = linked ? Number(linked.cash_amount || 0) + Number(linked.card_amount || 0) : 0;
        const creditAmount = Number(sale.amount || 0);
        const collectedAmount = Math.max(0, creditAmount - Number(sale.remaining_amount || 0));

        // تاريخ العملية يعرض أولًا، بينما تواريخ التحصيلات تظهر في سجلها المستقل أدناه.
        document.getElementById('previewContent').innerHTML = `
            <div class="ui-card-muted p-3 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="ui-title text-sm font-bold">${escapeHtml(sale.credit_note || 'أجل بدون ملاحظة')}</div>
                    ${paymentStatus(sale)}
                </div>
                <div class="grid grid-cols-1 gap-2 ui-text-caption ui-text-soft">
                    <div>تاريخ العملية: <span class="ui-title">${escapeHtml(sale.date || '-')}</span></div>
                    <div>الموظف: <span class="ui-title">${escapeHtml(sale.employee_name || '-')}</span></div>
                </div>
            </div>

            <details class="ui-card-muted p-3" open>
                <summary class="cursor-pointer ui-title text-sm font-bold">تفاصيل الفاتورة <span class="ui-text-muted ui-text-caption font-normal">#${linked ? linked.id : sale.id}</span></summary>
                ${linked ? `
                    <div class="mt-3 grid grid-cols-1 gap-2 ui-text-caption ui-text-soft">
                        <div>تاريخ العملية: <span class="ui-title">${escapeHtml(linked.business_date || linked.created_at || '-')}</span></div>
                        <div>المحاسب المنفذ: <span class="ui-title">${escapeHtml(linked.accountant_name || '-')}</span></div>
                    </div>
                    <div class="mt-3 rounded-lg border ui-border ui-input-bg p-2">
                        ${renderSaleItems(linked.items || [], linked.labor_total, linked.description)}
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2 text-center ui-text-caption">
                        ${renderFinancialCards([
                            {label: 'الإجمالي', value: linked.final_total, className: 'ui-title'},
                            {label: 'كاش', value: linked.cash_amount, className: 'ui-status-success'},
                            {label: 'شبكة', value: linked.card_amount, className: 'ui-status-info'},
                            {label: 'مكس', value: mixedTotal, className: 'ui-status-info'},
                            {label: 'شغل يد', value: linked.labor_total, className: 'ui-status-warning'},
                            {label: 'المدفوع', value: linked.paid_amount, className: 'ui-status-success'},
                            {label: 'الأجل المسجل', value: creditAmount, className: 'ui-status-warning'},
                            {label: 'المتبقي', value: sale.remaining_amount, className: 'ui-status-danger'},
                        ])}
                    </div>
                ` : '<p class="mt-3 ui-text-muted ui-text-caption">لا توجد عملية بيع مرتبطة محفوظة لهذا الأجل.</p>'}
            </details>

            <div class="ui-card-muted p-3">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="ui-title text-sm font-bold">التحصيلات</h4>
                    <div class="ui-text-soft ui-text-caption">المحصّل: <span class="ui-status-success font-bold">${money(collectedAmount)}</span></div>
                </div>
                ${renderPayments(sale.collection_payments || [])}
            </div>
        `;

        document.getElementById('previewModal').classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('previewModal').classList.add('hidden');
        document.getElementById('collectionModal')?.classList.remove('ui-modal-suspended');
    }

        function closeCollectionModal() {
            document.getElementById('collectionModal').classList.add('hidden');
        }

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || config.csrfToken;
        }

        // رسائل التحصيل تستخدم نافذة SweetAlert الوسطية بدل toast طرفي يصعب ملاحظته على اللمس.
        function showCollectionToast(icon, title) {
            return Swal.fire({
                icon,
                title,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            });
        }

        async function confirmCollection(title, text, confirmButtonText) {
            const result = await Swal.fire({
                icon: 'question',
                title,
                text,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText: 'إلغاء',
                confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-brand').trim(),
                cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-danger-text').trim(),
                background: getComputedStyle(document.documentElement).getPropertyValue('--ui-surface').trim(),
                color: getComputedStyle(document.documentElement).getPropertyValue('--ui-text').trim(),
            });

            return result.isConfirmed;
        }

        async function requestCollectionPaymentDetails(amount) {
            const total = Number(amount || 0);
            const formatTotal = total.toFixed(2);
            const paymentOptionButton = (method, icon, label, hint, active = false) => `
                <button type="button" data-method="${method}" class="ui-payment-option ${active ? 'is-active' : ''} flex flex-col items-center justify-center gap-1 border transition">
                    <span class="ui-payment-option-icon">
                        <i class="${icon} text-sm"></i>
                    </span>
                    <span class="text-sm font-bold">${label}</span>
                    <span class="ui-text-caption opacity-70">${hint}</span>
                </button>
            `;

            const result = await Swal.fire({
                title: 'نوع التحصيل',
                html: `
    <div class="text-right space-y-4">
                        <div class="grid grid-cols-3 gap-2" role="group" aria-label="طريقة السداد">
                            ${paymentOptionButton('cash', 'fa-solid fa-money-bill-wave', 'كاش', 'يسلم للمالك', true)}
                            ${paymentOptionButton('card', 'fa-solid fa-credit-card', 'شبكة', 'يدخل شبكة')}
                            ${paymentOptionButton('mixed', 'fa-solid fa-money-bill-transfer', 'ميكس', 'كاش + شبكة')}
                        </div>
                        <input id="collectionPaymentMethod" type="hidden" value="cash">

                        <div class="rounded-2xl border ui-border ui-input-bg p-3">
                            <label id="collectionCollectedLabel" class="mb-1 block ui-text-caption ui-text-soft">المبلغ المحصل كاش</label>
                            <div class="relative">
                                <input id="collectionCollectedAmount" type="number" step="0.01" min="0" class="collection-payment-field w-full rounded-xl border ui-border ui-input-bg p-2 pl-12 ui-title" value="${formatTotal}" readonly>
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 ui-text-caption ui-text-muted">ريال</span>
                            </div>
                            <p id="collectionCollectedHint" class="mt-1 ui-text-caption ui-text-muted">هذا هو مبلغ التحصيل المحدد لهذه العملية.</p>
                        </div>

                        <div id="collectionSplitWrapper" class="hidden grid grid-cols-2 gap-2">
                            <div class="rounded-2xl border ui-status-success-border ui-status-success-bg p-3">
                                <label class="mb-1 block ui-text-caption ui-status-success"><i class="fa-solid fa-money-bill-wave ml-1"></i> مبلغ الكاش</label>
                                <input id="collectionCashAmount" type="number" step="0.01" min="0" class="w-full rounded-xl border ui-status-success-border ui-input-bg p-2 ui-title">
                            </div>
                            <div class="rounded-2xl border ui-status-info-border ui-status-info-bg p-3">
                                <label class="mb-1 block ui-text-caption ui-status-info"><i class="fa-solid fa-credit-card ml-1"></i> مبلغ الشبكة</label>
                                <input id="collectionCardAmount" type="number" step="0.01" min="0" class="w-full rounded-xl border ui-status-info-border ui-input-bg p-2 ui-title">
                            </div>
                        </div>
                        <p class="ui-text-caption ui-text-soft">الكاش يدخل في تسليم المحاسب للمالك، والشبكة تظهر كتحصيل شبكة ضمن الحسابات.</p>
                    </div>
                `,
                confirmButtonText: 'متابعة',
                cancelButtonText: 'إلغاء',
                showCancelButton: true,
                background: getComputedStyle(document.documentElement).getPropertyValue('--ui-surface').trim(),
                color: getComputedStyle(document.documentElement).getPropertyValue('--ui-text').trim(),
                didOpen: () => {
                    const methodInput = document.getElementById('collectionPaymentMethod');
                    const wrapper = document.getElementById('collectionSplitWrapper');
                    const collectedInput = document.getElementById('collectionCollectedAmount');
                    const collectedLabel = document.getElementById('collectionCollectedLabel');
                    const collectedHint = document.getElementById('collectionCollectedHint');
                    const cashInput = document.getElementById('collectionCashAmount');
                    const cardInput = document.getElementById('collectionCardAmount');
                    const optionButtons = Array.from(document.querySelectorAll('.ui-payment-option[data-method]'));

                    const syncMixedTotal = () => {
                        const cash = Number(cashInput?.value || 0);
                        const card = Number(cardInput?.value || 0);
                        if (collectedInput) collectedInput.value = cash + card > 0 ? (cash + card).toFixed(2) : '';
                    };

                    const setMethod = (method) => {
                        if (methodInput) methodInput.value = method;
                        optionButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.method === method));
                        wrapper?.classList.toggle('hidden', method !== 'mixed');
                        if (collectedInput) {
                            collectedInput.value = formatTotal;
                            collectedInput.readOnly = true;
                        }
                        if (collectedLabel) {
                            collectedLabel.textContent = method === 'card' ? 'المبلغ المحصل شبكة' : (method === 'mixed' ? 'المبلغ المحصل' : 'المبلغ المحصل كاش');
                        }
                        if (collectedHint) {
                            collectedHint.textContent = method === 'mixed'
                                ? 'هذا الحقل يحسب تلقائياً من حقلي الكاش والشبكة ولا يمكن الكتابة فيه.'
                                : 'هذا هو مبلغ التحصيل المحدد لهذه العملية.';
                        }
                        if (method === 'mixed') {
                            if (cashInput) cashInput.value = '';
                            if (cardInput) cardInput.value = '';
                            syncMixedTotal();
                        }
                    };

                    optionButtons.forEach((button) => button.addEventListener('click', () => setMethod(button.dataset.method || 'cash')));
                    cashInput?.addEventListener('input', syncMixedTotal);
                    cardInput?.addEventListener('input', syncMixedTotal);
                    setMethod('cash');
                },
                preConfirm: () => {
                    const method = document.getElementById('collectionPaymentMethod')?.value || 'cash';
                    const cash = method === 'card' ? 0 : (method === 'mixed' ? Number(document.getElementById('collectionCashAmount')?.value || 0) : total);
                    const card = method === 'cash' ? 0 : (method === 'mixed' ? Number(document.getElementById('collectionCardAmount')?.value || 0) : total);
                    if (method === 'mixed' && Math.abs((cash + card) - total) > 0.01) {
                        Swal.showValidationMessage('في الميكس يجب أن يساوي مجموع الكاش والشبكة مبلغ التحصيل.');
                        return false;
                    }
                    return { payment_method: method, cash_amount: cash.toFixed(2), card_amount: card.toFixed(2) };
                },
            });

            return result.isConfirmed ? result.value : null;
        }

        async function submitCollectionRequest(route, fields = {}, button = null) {
            if (button) {
                button.disabled = true;
                button.dataset.originalText = button.textContent.trim();
                button.textContent = 'جاري التنفيذ...';
            }

            const formData = new FormData();
            formData.append('_token', csrfToken());
            Object.entries(fields).forEach(([name, value]) => formData.append(name, value));

            try {
                const response = await fetch(route, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    await showCollectionToast('error', data.error || data.message || 'تعذر تنفيذ التحصيل');
                    return;
                }

                await showCollectionToast('success', data.message || 'تم التحصيل');
                setTimeout(() => location.reload(), 900);
            } catch (error) {
                await showCollectionToast('error', 'تعذر الاتصال بالخادم');
            } finally {
                document.getElementById('collectionModal')?.classList.remove('ui-modal-suspended');
                if (button) {
                    button.disabled = false;
                    button.textContent = button.dataset.originalText || 'تنفيذ';
                }
            }
        }

        async function collectCreditSaleFull(route, amount, button = null) {
            const collectionAmount = Number(amount || 0);
            const collectionModal = document.getElementById('collectionModal');
            collectionModal?.classList.add('ui-modal-suspended');
            if (await confirmCollection('تأكيد تحصيل البيع الآجل كاملاً؟', `سيتم تحصيل ${collectionAmount.toFixed(2)} ريال وإغلاق العملية.`, 'تحصيل كامل')) {
                const paymentDetails = await requestCollectionPaymentDetails(collectionAmount);
                if (!paymentDetails) {
                    collectionModal?.classList.remove('ui-modal-suspended');
                    return;
                }
                submitCollectionRequest(route, paymentDetails, button);
                return;
            }
            collectionModal?.classList.remove('ui-modal-suspended');
        }

        function openPartialModal(saleId, maxAmount) {
            const form = document.getElementById('partialForm');
            const amountInput = document.getElementById('partialAmount');
            const amountLabel = document.getElementById('partialAmountLabel');
            const paymentMethodInput = document.getElementById('partialPaymentMethod');
            const splitWrapper = document.getElementById('partialSplitWrapper');
            const cashInput = document.getElementById('partialCashAmount');
            const cardInput = document.getElementById('partialCardAmount');
            const paymentHint = document.getElementById('partialPaymentHint');
            const paymentButtons = Array.from(document.querySelectorAll('[data-partial-payment-method]'));
            amountInput.value = '';
            const maximumAmount = Number(maxAmount || 0);
            amountInput.max = maximumAmount.toFixed(2);
            const amountLimit = document.getElementById('partialAmountLimit');
            if (amountLimit) {
                amountLimit.textContent = `الحد الأقصى للتحصيل الجزئي: ${maximumAmount.toFixed(2)} ريال`;
            }

            const stylePartialPaymentButtons = (method) => {
                paymentButtons.forEach((button) => {
                    button.classList.toggle('is-active', button.dataset.partialPaymentMethod === method);
                });
            };

            const syncMixedAmount = () => {
                const cash = Number(cashInput?.value || 0);
                const card = Number(cardInput?.value || 0);
                amountInput.value = cash + card > 0 ? (cash + card).toFixed(2) : '';
            };

            const setPartialPaymentMethod = (method) => {
                if (paymentMethodInput) paymentMethodInput.value = method;
                stylePartialPaymentButtons(method);
                splitWrapper?.classList.toggle('hidden', method !== 'mixed');
                amountInput.disabled = method === 'mixed';
                if (amountLabel) {
                    amountLabel.textContent = method === 'card' ? 'المبلغ المحصل شبكة' : (method === 'mixed' ? 'المبلغ المحصل' : 'المبلغ المحصل كاش');
                }
                if (paymentHint) {
                    paymentHint.textContent = method === 'mixed'
                        ? 'اكتب مبلغ الكاش ومبلغ الشبكة، وسيحسب حقل المبلغ المحصل تلقائياً.'
                        : 'اكتب مبلغ التحصيل ثم اضغط تأكيد.';
                }
                if (method === 'mixed') {
                    const currentAmount = Number(amountInput.value || 0);
                    if (cashInput) cashInput.value = '';
                    if (cardInput) cardInput.value = '';
                    syncMixedAmount();
                }
            };

            amountInput.oninput = function() {
                const enteredAmount = Number(amountInput.value || 0);
                if (enteredAmount > maximumAmount) {
                    amountInput.value = maximumAmount.toFixed(2);
                    showCollectionToast('warning', 'لا يمكن تجاوز المبلغ المتبقي للأجل');
                }
            };
            if (cashInput) cashInput.oninput = syncMixedAmount;
            if (cardInput) cardInput.oninput = syncMixedAmount;
            paymentButtons.forEach((button) => {
                button.onclick = () => setPartialPaymentMethod(button.dataset.partialPaymentMethod || 'cash');
            });
            setPartialPaymentMethod('cash');

            const route = config.collectionRouteTemplate.replace('SALE', saleId);

            form.onsubmit = async function(e) {
                e.preventDefault();
                const method = paymentMethodInput?.value || 'cash';
                const amount = Number(amountInput.value || 0);
                const maximumAmount = Number(maxAmount || 0);

                if (amount <= 0 || amount > maximumAmount) {
                    await showCollectionToast('error', `المبلغ غير صالح، الحد الأقصى ${maximumAmount.toFixed(2)} ريال`);
                    return;
                }

                const cash = method === 'card' ? 0 : (method === 'mixed' ? Number(cashInput?.value || 0) : amount);
                const card = method === 'cash' ? 0 : (method === 'mixed' ? Number(cardInput?.value || 0) : amount);
                if (method === 'mixed' && Math.abs((cash + card) - amount) > 0.01) {
                    await showCollectionToast('error', 'في الميكس يجب أن يساوي مجموع الكاش والشبكة مبلغ التحصيل.');
                    return;
                }

                submitCollectionRequest(route, {
                    amount,
                    payment_method: method,
                    cash_amount: cash.toFixed(2),
                    card_amount: card.toFixed(2),
                }, form.querySelector('button[type="submit"]'));
            };

            document.getElementById('partialModal').classList.remove('hidden');
        }

        function closePartialModal() {
            document.getElementById('partialModal').classList.add('hidden');
        }

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-sensitive-action="collection.full"]');
            if (!button || !root.isConnected) return;

            event.preventDefault();
            collectCreditSaleFull(button.dataset.route, Number(button.dataset.amount), button);
        });

    // تبقى هذه الأسماء متاحة مؤقتًا لوحدة تفويض عقود data-sensitive-* دون إعادة منطق التحصيل إلى Blade.
    Object.assign(window, {
        openCollectionModal,
        closeCollectionModal,
        openPreviewModal,
        closePreviewModal,
        collectCreditSaleFull,
        openPartialModal,
        closePartialModal,
    });
}
