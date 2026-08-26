// تستخرج هذه الوحدة سلوك المديونيات كما هو، وتقرأ المسارات فقط من عقد Blade الآمن.
const root = document.querySelector('[data-debt-operations-config]');

if (root) {
    const config = JSON.parse(root.dataset.debtOperationsConfig || '{}');
    let currentEmpId = null;
    let currentEmpName = '';

    function openDebtModal(empId, empName, hasDebt) {
        currentEmpId = empId;
        currentEmpName = empName;

        document.getElementById('empNameDisplay').textContent = empName;
        const routeTemplate = config.debtStoreRouteTemplate;
        document.getElementById('debtForm').action = routeTemplate.replace('ID', empId);

        if (hasDebt) {
            document.getElementById('debtActions').classList.remove('hidden');
            document.getElementById('addOnly').classList.add('hidden');
            document.getElementById('modalTitle').textContent = 'إدارة المديونية';
        } else {
            document.getElementById('addOnly').classList.remove('hidden');
            document.getElementById('debtActions').classList.add('hidden');
            document.getElementById('modalTitle').textContent = 'إضافة مديونية';
        }

        const modal = document.getElementById('debtModal');
        modal.classList.remove('hidden');
        document.body.classList.add('ui-scroll-lock');
    }

    function closeDebtModal() {
        const modal = document.getElementById('debtModal');
        modal.classList.add('hidden');
        document.body.classList.remove('ui-scroll-lock');
        document.getElementById('debtForm').reset();
    }

    function openCollectModal() {
        closeDebtModal();

        document.getElementById('collectModal').classList.remove('hidden');
        document.getElementById('collectEmpName').textContent = currentEmpName;
        document.body.classList.add('ui-scroll-lock');

        const url = config.debtListRouteTemplate.replace('EMP_ID', currentEmpId);

        fetch(url)
            .then(res => res.json())
            .then(data => {
                let html = '';

                if (data.length === 0) {
                    html = `
                        <div class="text-center py-6">
                            <div class="w-12 h-12 mx-auto mb-2 rounded-full ui-surface-strong-bg flex items-center justify-center">
                                <svg class="w-6 h-6 ui-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <p class="ui-text-soft ui-text-caption sm:text-sm">لا توجد مديونيات</p>
                        </div>
                    `;
                } else {
                    data.forEach(d => {
                        const amount = parseFloat(d.amount).toFixed(2);
                        const isCollected = Number(d.amount || 0) <= 0 || d.status === 'deducted';
                        html += `
                            <div class="ui-surface-strong-bg border ui-border rounded-lg sm:rounded-xl p-2 sm:p-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full ui-status-info-bg border ui-status-info-border flex items-center justify-center flex-shrink-0">
                                                <span class="ui-status-info font-bold ui-text-caption sm:text-sm">${amount.split('.')[0]}</span>
                                            </div>
                                            <div>
                                                <div class="ui-title font-bold text-sm sm:text-base">${amount} ﷼</div>
                                                <div class="ui-text-soft ui-text-caption">تاريخ العملية: ${d.date}</div>
                                                ${d.description ? `<div class="ui-text-soft ui-text-caption mt-0.5">${d.description}</div>` : ''}
                                                ${d.has_partial_collection && !isCollected ? `<div class="mt-1 inline-flex items-center gap-1 rounded-full border ui-status-warning-bg ui-status-warning-border px-2 py-0.5 ui-text-caption font-bold ui-status-warning">⚠️ تم تحصيل جزء منها${Number(d.collected_amount || 0) > 0 ? ` (${Number(d.collected_amount || 0).toFixed(2)} ﷼)` : ''}</div>` : ''}
                                                ${isCollected ? `<div class="ui-status-success ui-text-caption font-bold">${d.collected_at ? `تاريخ التحصيل: ${d.collected_at}` : 'تم تحصيلها'}</div>` : ''}
                                            </div>
                                        </div>
                                    </div>

                                    ${isCollected ? `
                                        <span class="rounded-lg border ui-status-success-bg ui-status-success-border px-3 py-1.5 ui-text-caption font-bold ui-status-success">مغلقة</span>
                                    ` : `
                                    <div class="flex gap-1 sm:gap-2">
                                        <!-- التحصيل الكامل والجزئي مرتبطان مركزيًا مع تمرير نفس المعرّف والمبلغ السابقين. -->
                                        <button type="button" data-sensitive-action="debt.full" data-debt-id="${d.id}" data-amount="${Number(d.amount || 0)}"
                                                class="ui-btn ui-btn-success flex-1 sm:flex-none px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                                            كامل
                                        </button>
                                        <button type="button" data-sensitive-action="debt.partial" data-debt-id="${d.id}" data-amount="${Number(d.amount || 0)}"
                                                class="ui-btn ui-btn-warning flex-1 sm:flex-none px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                                            جزئي
                                        </button>
                                    </div>
                                    `}
                                </div>

                            </div>
                        `;
                    });
                }

                document.getElementById('debtsList').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('debtsList').innerHTML = `
                    <div class="ui-status-danger-bg border ui-status-danger-border rounded-lg p-3 text-center">
                        <p class="ui-status-danger ui-text-caption sm:text-sm">حدث خطأ في التحميل</p>
                    </div>
                `;
            });
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function showDebtAlert(icon, title, text = '') {
        return Swal.fire({
            icon,
            title,
            text,
            confirmButtonText: 'حسناً',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-info-text').trim(),
            background: getComputedStyle(document.documentElement).getPropertyValue('--ui-surface').trim(),
            color: getComputedStyle(document.documentElement).getPropertyValue('--ui-text').trim(),
        });
    }

    async function confirmDebtAction(title, text, confirmButtonText = 'تأكيد') {
        const result = await Swal.fire({
            icon: 'question',
            title,
            text,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'إلغاء',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-success-text').trim(),
            cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--ui-danger-text').trim(),
            background: getComputedStyle(document.documentElement).getPropertyValue('--ui-surface').trim(),
            color: getComputedStyle(document.documentElement).getPropertyValue('--ui-text').trim(),
        });

        return result.isConfirmed;
    }

    async function debtPaymentFields(maxAmount, isFull = true) {
        const total = Number(maxAmount || 0);
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
                    <input id="DebtPaymentMethod" type="hidden" value="cash">

                    <div class="rounded-2xl border ui-border ui-input-bg p-3">
                        <label id="DebtCollectedLabel" class="mb-1 block ui-text-caption ui-text-soft">المبلغ المحصل كاش</label>
                        <div class="relative">
                            <input id="DebtCollectedAmount" type="number" step="0.01" min="0" max="${formatTotal}" class="debt-payment-field w-full rounded-xl border ui-border ui-input-bg p-2 pl-12 ui-title" value="${formatTotal}" ${isFull ? 'readonly' : ''}>
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 ui-text-caption ui-text-muted">ريال</span>
                        </div>
                        <p id="DebtCollectedHint" class="mt-1 ui-text-caption ui-text-muted">${isFull ? 'هذا هو مبلغ التحصيل الكامل لهذه المديونية.' : 'اكتب مبلغ التحصيل الجزئي هنا.'}</p>
                    </div>

                    <div id="DebtSplitWrapper" class="hidden grid grid-cols-2 gap-2">
                        <div class="rounded-2xl border ui-status-success-border ui-status-success-bg p-3">
                            <label class="mb-1 block ui-text-caption ui-status-success"><i class="fa-solid fa-money-bill-wave ml-1"></i> مبلغ الكاش</label>
                            <input id="DebtCashAmount" type="number" step="0.01" min="0" class="w-full rounded-xl border ui-status-success-border ui-input-bg p-2 ui-title">
                        </div>
                        <div class="rounded-2xl border ui-status-info-border ui-status-info-bg p-3">
                            <label class="mb-1 block ui-text-caption ui-status-info"><i class="fa-solid fa-credit-card ml-1"></i> مبلغ الشبكة</label>
                            <input id="DebtCardAmount" type="number" step="0.01" min="0" class="w-full rounded-xl border ui-status-info-border ui-input-bg p-2 ui-title">
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
                const methodInput = document.getElementById('DebtPaymentMethod');
                const wrapper = document.getElementById('DebtSplitWrapper');
                const collectedInput = document.getElementById('DebtCollectedAmount');
                const collectedLabel = document.getElementById('DebtCollectedLabel');
                const collectedHint = document.getElementById('DebtCollectedHint');
                const cashInput = document.getElementById('DebtCashAmount');
                const cardInput = document.getElementById('DebtCardAmount');
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
                        collectedInput.value = (method === 'mixed' || isFull) ? formatTotal : '';
                        collectedInput.readOnly = method === 'mixed' || isFull;
                    }
                    if (collectedLabel) collectedLabel.textContent = method === 'card' ? 'المبلغ المحصل شبكة' : (method === 'mixed' ? 'المبلغ المحصل' : 'المبلغ المحصل كاش');
                    if (collectedHint) collectedHint.textContent = method === 'mixed'
                        ? 'هذا الحقل يحسب تلقائياً من حقلي الكاش والشبكة ولا يمكن الكتابة فيه.'
                        : (isFull ? 'هذا هو مبلغ التحصيل الكامل لهذه المديونية.' : 'اكتب مبلغ التحصيل الجزئي، ويجب ألا يتجاوز المتبقي.');
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
                const method = document.getElementById('DebtPaymentMethod')?.value || 'cash';
                const enteredAmount = method === 'mixed'
                    ? Number(document.getElementById('DebtCollectedAmount')?.value || 0)
                    : (isFull ? total : Number(document.getElementById('DebtCollectedAmount')?.value || 0));
                const cash = method === 'card' ? 0 : (method === 'mixed' ? Number(document.getElementById('DebtCashAmount')?.value || 0) : enteredAmount);
                const card = method === 'cash' ? 0 : (method === 'mixed' ? Number(document.getElementById('DebtCardAmount')?.value || 0) : enteredAmount);
                const collected = method === 'mixed' ? cash + card : enteredAmount;
                if (collected <= 0 || collected > total) {
                    Swal.showValidationMessage(`مبلغ التحصيل يجب أن يكون أكبر من صفر ولا يتجاوز ${formatTotal} ريال.`);
                    return false;
                }
                return { amount: collected.toFixed(2), payment_method: method, cash_amount: cash.toFixed(2), card_amount: card.toFixed(2) };
            },
        });

        return result.isConfirmed ? result.value : null;
    }

    async function collectFull(id, amount, button = null) {
        const fields = await debtPaymentFields(amount, true);
        if (!fields) return;

        if (await confirmDebtAction('تأكيد تحصيل المديونية كاملة؟', 'سيتم تسجيل عملية تحصيل كاملة وتصفير هذه المديونية.', 'تحصيل كامل')) {
            submitDebtCollection(`${config.fullCollectionUrl}/${id}`, fields, button);
        }
    }

    async function collectPartial(id, maxAmount, button = null) {
        const fields = await debtPaymentFields(maxAmount, false);
        if (!fields) return;

        const amount = Number(fields.amount || 0);
        if (await confirmDebtAction('تأكيد التحصيل الجزئي؟', `سيتم تحصيل ${amount.toFixed(2)} ريال وتخفيض المديونية بهذا المبلغ.`, 'تحصيل جزئي')) {
            submitDebtCollection(`${config.partialCollectionUrl}/${id}`, fields, button);
        }
    }

    function submitDebtCollection(action, fields = {}, button = null) {
        if (button) {
            button.disabled = true;
            button.dataset.originalText = button.textContent.trim();
            button.textContent = 'جاري التنفيذ...';
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken();
        form.appendChild(tokenInput);

        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function closeCollectModal() {
        const modal = document.getElementById('collectModal');
        modal.classList.add('hidden');
        document.body.classList.remove('ui-scroll-lock');
    }

    document.getElementById('debtForm').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = 'جاري الحفظ...';
    });

    // تتاح هذه الدوال لوحدة تفويض data-sensitive-* من دون إعادة أي منطق إلى القالب.
    Object.assign(window, {
        openDebtModal,
        closeDebtModal,
        openCollectModal,
        closeCollectModal,
        collectFull,
        collectPartial,
    });
}
