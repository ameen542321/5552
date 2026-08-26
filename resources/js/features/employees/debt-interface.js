// يحافظ على حقول تحصيل مديونية الموظف وطرق الدفع كما هي، وينقل التنفيذ خارج Blade.
if (document.querySelector('[data-employee-debt-interface]')) {
    function employeeDebtSwalTheme() {
        const styles = getComputedStyle(document.documentElement);
        return {
            background: styles.getPropertyValue('--ui-surface').trim(),
            color: styles.getPropertyValue('--ui-text').trim(),
            confirmButtonColor: styles.getPropertyValue('--ui-brand').trim(),
            cancelButtonColor: styles.getPropertyValue('--ui-danger-text').trim(),
        };
    }

    function confirmEmployeeOperationForm(event, title, text) {
        if (typeof Swal === 'undefined') {
            return window.confirm(text || title);
        }

        event.preventDefault();
        Swal.fire({
            icon: 'question',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: 'تأكيد',
            cancelButtonText: 'إلغاء',
            ...employeeDebtSwalTheme(),
        }).then((result) => {
            if (result.isConfirmed) {
                event.target.submit();
            }
        });

        return false;
    }

    function showEmployeeOperationAlert(icon, title, text) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({ icon, title, text, confirmButtonText: 'حسناً', ...employeeDebtSwalTheme() });
        }

        window.alert(text || title);
    }

    async function employeeDebtPaymentFields(maxAmount, isFull = true) {
        const total = Number(maxAmount || 0);
        const formatTotal = total.toFixed(2);
        const paymentOptionButton = (method, icon, label, hint, active = false) => `
            <button type="button" data-method="${method}" class="employee-debt-payment-option ${active ? 'is-active' : ''} flex flex-col items-center justify-center gap-1 rounded-xl border p-2 transition">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-current/25 bg-current/10">
                    <i class="${icon} text-sm"></i>
                </span>
                <span class="ui-text-caption font-bold">${label}</span>
                <span class="ui-text-caption opacity-70">${hint}</span>
            </button>
        `;

        const result = await Swal.fire({
            title: 'نوع التحصيل',
            html: `
                <div class="text-right space-y-3">
                    <div class="grid grid-cols-3 gap-2" role="group" aria-label="طريقة السداد">
                        ${paymentOptionButton('cash', 'fa-solid fa-money-bill-wave', 'كاش', 'يسلم للمالك', true)}
                        ${paymentOptionButton('card', 'fa-solid fa-credit-card', 'شبكة', 'يدخل شبكة')}
                        ${paymentOptionButton('mixed', 'fa-solid fa-money-bill-transfer', 'ميكس', 'كاش + شبكة')}
                    </div>
                    <input id="employeeDebtPaymentMethod" type="hidden" value="cash">

                    <div class="rounded-xl ui-border ui-surface-muted-bg p-3">
                        <label id="employeeDebtCollectedLabel" class="mb-1 block ui-text-caption ui-text-soft">المبلغ المحصل كاش</label>
                        <div class="relative">
                            <input id="employeeDebtCollectedAmount" type="number" step="0.01" min="0" max="${formatTotal}" class="debt-payment-field ui-input w-full rounded-xl p-2 pl-12" value="${formatTotal}" ${isFull ? 'readonly' : ''}>
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 ui-text-caption ui-text-muted">ريال</span>
                        </div>
                        <p id="employeeDebtCollectedHint" class="mt-1 ui-text-caption ui-text-muted">${isFull ? 'هذا هو مبلغ التحصيل الكامل لهذه المديونية.' : 'اكتب مبلغ التحصيل الجزئي هنا.'}</p>
                    </div>

                    <div id="employeeDebtSplitWrapper" class="hidden grid grid-cols-2 gap-2">
                        <div class="rounded-xl ui-border ui-status-success-bg p-3">
                            <label class="mb-1 block ui-text-caption ui-status-success"><i class="fa-solid fa-money-bill-wave ml-1"></i> مبلغ الكاش</label>
                            <input id="employeeDebtCashAmount" type="number" step="0.01" min="0" class="ui-input w-full rounded-xl p-2">
                        </div>
                        <div class="rounded-xl ui-border ui-status-info-bg p-3">
                            <label class="mb-1 block ui-text-caption ui-status-info"><i class="fa-solid fa-credit-card ml-1"></i> مبلغ الشبكة</label>
                            <input id="employeeDebtCardAmount" type="number" step="0.01" min="0" class="ui-input w-full rounded-xl p-2">
                        </div>
                    </div>
                    <p class="ui-text-caption ui-text-soft">الكاش يدخل في تسليم المحاسب للمالك، والشبكة تظهر كتحصيل شبكة ضمن الحسابات.</p>
                </div>
            `,
            confirmButtonText: 'متابعة',
            cancelButtonText: 'إلغاء',
            showCancelButton: true,
            ...employeeDebtSwalTheme(),
            didOpen: () => {
                const methodInput = document.getElementById('employeeDebtPaymentMethod');
                const wrapper = document.getElementById('employeeDebtSplitWrapper');
                const collectedInput = document.getElementById('employeeDebtCollectedAmount');
                const collectedLabel = document.getElementById('employeeDebtCollectedLabel');
                const collectedHint = document.getElementById('employeeDebtCollectedHint');
                const cashInput = document.getElementById('employeeDebtCashAmount');
                const cardInput = document.getElementById('employeeDebtCardAmount');
                const optionButtons = Array.from(document.querySelectorAll('.employee-debt-payment-option'));

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
                const method = document.getElementById('employeeDebtPaymentMethod')?.value || 'cash';
                const enteredAmount = method === 'mixed'
                    ? Number(document.getElementById('employeeDebtCollectedAmount')?.value || 0)
                    : (isFull ? total : Number(document.getElementById('employeeDebtCollectedAmount')?.value || 0));
                const cash = method === 'card' ? 0 : (method === 'mixed' ? Number(document.getElementById('employeeDebtCashAmount')?.value || 0) : enteredAmount);
                const card = method === 'cash' ? 0 : (method === 'mixed' ? Number(document.getElementById('employeeDebtCardAmount')?.value || 0) : enteredAmount);
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

    async function submitEmployeeDebtCollectionWithPayment(action, amount, fields = {}, title = 'تأكيد التحصيل؟', text = '', isFull = true) {
        const paymentFields = await employeeDebtPaymentFields(Number(amount || 0), isFull);
        if (!paymentFields) return;

        const confirmed = await confirmEmployeeOperationAsync(title, text || 'سيتم تسجيل التحصيل حسب نوع الدفع المختار.');
        if (!confirmed) return;

        submitDebtCollection(action, { ...fields, amount: paymentFields.amount, ...paymentFields });
    }

    function confirmEmployeeOperationAsync(title, text) {
        if (typeof Swal === 'undefined') {
            return Promise.resolve(window.confirm(text || title));
        }

        return Swal.fire({
            icon: 'question',
            title,
            text,
            showCancelButton: true,
            confirmButtonText: 'تأكيد',
            cancelButtonText: 'إلغاء',
            ...employeeDebtSwalTheme(),
        }).then((result) => result.isConfirmed);
    }

    function submitDebtCollection(action, fields = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = document.querySelector('meta[name="csrf-token"]')?.content || '';
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

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-employee-debt-collect]');
        if (!button) return;

        submitEmployeeDebtCollectionWithPayment(
            button.dataset.action,
            Number(button.dataset.amount || 0),
            {},
            button.dataset.confirmTitle,
            button.dataset.confirmText,
            button.dataset.full === 'true',
        );
    });
}
