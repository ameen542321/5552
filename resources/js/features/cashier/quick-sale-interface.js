// تحافظ الوحدة على حسابات البيع والتحقق كما كانت، وتقرأ بيانات الخادم من عقد Blade فقط.
const configRoot = document.querySelector('[data-quick-sale-config]');
const quickSaleConfig = configRoot ? JSON.parse(configRoot.dataset.quickSaleConfig || '{}') : {};

window.quickSale = function quickSale() {
    return {
        search: '',
        results: [],
        searchResultsOpen: false,
        infoModal: null,
        cart: [],
        expandedTintGroups: {},
        labor_total: 0,
        paid_amount: 0,
        agreed_credit_total: 0,
        tax_rate: 0,
        sale_type: '',
        employee_id: '',
        creditPersons: [],
        description: '',
        credit_note: '',
        // خيارات وصف عمل اليد الأكثر استخداماً (تظهر كأزرار أعلى حقل الوصف).
        laborDescriptionOptions: quickSaleConfig.laborDescriptionOptions || ['تضليل', 'تجليد', 'شغل يد'],
        items_json: '',
        has_invoice: false,
        hasPartialCredit: false,
        partial_credit_amount: 0,
        mixedCash: '',
        mixedCard: '',
        showingFeatured: true,
        hasStoreTaxNumber: Boolean(quickSaleConfig.hasStoreTaxNumber),
        clientOperationId: '',
        pendingQuickSaleOperation: null,
        pendingWarningVisible: false,
        pendingWarningTimer: null,
        pendingStatusChecking: false,
        saleSubmitting: false,
        hasApprovedTaxNumber: Boolean(quickSaleConfig.hasApprovedTaxNumber),

        init() {
            if (this.reloadIfRestoredFromHistory()) return;

            if (quickSaleConfig.clearPendingOnSuccess) {
                localStorage.removeItem(this.quickSalePendingKey());
            }
            this.restorePendingOperation();
            this.verifyPendingOperation(false);
            this.ensureClientOperationId();
            this.loadCreditPersons();
            this.loadFeaturedProducts();
            this.$nextTick(() => { this.$refs.searchInput.focus(); });
        },

        quickSalePendingKey() {
            return 'quick-sale-pending-operation';
        },

        quickSaleHistoryReloadKey() {
            return 'quick-sale-history-reload-in-progress';
        },

        reloadIfRestoredFromHistory() {
            const reloadKey = this.quickSaleHistoryReloadKey();
            const navigationEntry = performance.getEntriesByType?.('navigation')?.[0];
            const isBackForwardLoad = navigationEntry?.type === 'back_forward';

            if (sessionStorage.getItem(reloadKey) === '1') {
                sessionStorage.removeItem(reloadKey);
                return false;
            }

            if (isBackForwardLoad) {
                sessionStorage.setItem(reloadKey, '1');
                window.location.reload();
                return true;
            }

            window.addEventListener('pageshow', (event) => {
                if (!event.persisted || sessionStorage.getItem(reloadKey) === '1') return;

                sessionStorage.setItem(reloadKey, '1');
                window.location.reload();
            }, { once: true });

            return false;
        },

        generateClientOperationId() {
            if (window.crypto?.randomUUID) {
                return window.crypto.randomUUID();
            }

            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                const random = Math.random() * 16 | 0;
                const value = char === 'x' ? random : (random & 0x3 | 0x8);
                return value.toString(16);
            });
        },

        ensureClientOperationId() {
            if (!this.clientOperationId) {
                this.clientOperationId = this.generateClientOperationId();
            }
        },

        restorePendingOperation() {
            try {
                const raw = localStorage.getItem(this.quickSalePendingKey());
                const pending = raw ? JSON.parse(raw) : null;
                const savedAt = pending?.saved_at ? Date.parse(pending.saved_at) : 0;
                if (!pending?.id || !savedAt || Date.now() - savedAt > 10 * 60 * 1000) {
                    this.clearPendingOperation(false);
                    return;
                }
                this.pendingQuickSaleOperation = pending;
                this.clientOperationId = pending.id;
                this.schedulePendingWarning();
            } catch (error) {
                this.clearPendingOperation();
            }
        },

        rememberPendingOperation() {
            const pending = {
                id: this.clientOperationId,
                saved_at: new Date().toISOString(),
                total: Math.round(this.final_total || 0),
            };
            this.pendingQuickSaleOperation = pending;
            this.pendingWarningVisible = false;
            this.schedulePendingWarning();
            localStorage.setItem(this.quickSalePendingKey(), JSON.stringify(pending));
        },

        schedulePendingWarning() {
            window.clearTimeout(this.pendingWarningTimer);
            this.pendingWarningVisible = false;

            const savedAt = Date.parse(this.pendingQuickSaleOperation?.saved_at || '');
            if (!this.pendingQuickSaleOperation?.id || !Number.isFinite(savedAt)) return;

            const delay = Math.max(0, Number(quickSaleConfig.pendingWarningDelayMs || 60_000));
            const remaining = Math.max(0, delay - (Date.now() - savedAt));
            this.pendingWarningTimer = window.setTimeout(() => {
                this.pendingWarningVisible = Boolean(this.pendingQuickSaleOperation?.id);
            }, remaining);
        },

        clearPendingOperation(generateNewOperationId = true) {
            localStorage.removeItem(this.quickSalePendingKey());
            window.clearTimeout(this.pendingWarningTimer);
            this.pendingQuickSaleOperation = null;
            this.pendingWarningVisible = false;
            if (generateNewOperationId) {
                this.clientOperationId = this.generateClientOperationId();
            }
            this.saleSubmitting = false;
        },

        resetSaleFormAfterConfirmedPending() {
            this.cart = [];
            this.labor_total = 0;
            this.paid_amount = 0;
            this.mixedCash = '';
            this.mixedCard = '';
            this.sale_type = '';
            this.employee_id = '';
            this.description = '';
            this.credit_note = '';
            this.hasPartialCredit = false;
            this.partial_credit_amount = 0;
            this.agreed_credit_total = 0;
            this.items_json = '';
        },

        operationStatusUrl(operationId) {
            return `${quickSaleConfig.operationStatusBaseUrl}/${encodeURIComponent(operationId)}`;
        },

        async verifyPendingOperation(showResult = true) {
            if (!this.pendingQuickSaleOperation?.id) return;
            this.pendingStatusChecking = true;
            try {
                const response = await fetch(this.operationStatusUrl(this.pendingQuickSaleOperation.id), { cache: 'no-store' });
                const data = await response.json();

                if (data.status === 'completed') {
                    this.clearPendingOperation();
                    this.resetSaleFormAfterConfirmedPending();
                    if (showResult) {
                        return Swal.fire({
                            title: 'العملية مسجلة',
                            text: `تم تسجيل البيع بنجاح برقم #${data.sale_id}. لم يتم تكرار العملية.`,
                            icon: 'success',
                        });
                    }
                    return;
                }

                if (data.status === 'not_found') {
                    // لا نحذف معرّف العملية عند عدم العثور عليها؛ فقد يكون الطلب الأول ما زال قيد التنفيذ.
                    // إبقاء المعرّف نفسه يجعل أي إعادة إرسال آمنة بفضل قيد التفرد في قاعدة البيانات.
                    this.clientOperationId = this.pendingQuickSaleOperation.id;
                    this.saleSubmitting = false;
                    if (showResult) {
                        return Swal.fire({
                            title: 'لم تظهر نتيجة نهائية بعد',
                            text: 'سيبقى معرّف العملية محفوظًا. يمكنك الانتظار ثم فحص الحالة مجددًا، أو إعادة اعتماد العملية نفسها بأمان دون إنشاء معرّف جديد.',
                            icon: 'warning',
                        });
                    }
                    return;
                }
            } catch (error) {
                if (showResult) {
                    return Swal.fire({
                        title: 'تعذر الفحص',
                        text: 'الاتصال غير مستقر حالياً. لا تضغط اعتماد مرة أخرى قبل رجوع الاتصال وفحص الحالة.',
                        icon: 'error',
                    });
                }
            } finally {
                this.pendingStatusChecking = false;
            }
        },

        async checkPendingOperation() {
            return this.verifyPendingOperation(true);
        },

        preventWheelChange(event) {
            event.preventDefault();
            event.currentTarget.blur();
            window.scrollBy({ top: event.deltaY, left: event.deltaX, behavior: 'auto' });
        },

        async loadCreditPersons() {
            try {
                // نبني الرابط من المسار الحالي لتفادي أي تعارض في route helper داخل Blade.
                const creditPersonsEndpoint = window.location.pathname.replace(/\/quick-sale\/?$/, '/quick-sale/credit-persons');
                let res = await fetch(creditPersonsEndpoint);
                this.creditPersons = await res.json();
            } catch (e) { console.error('Error loading employees'); }
        },

        productSearchUrl(query) {
            const url = new URL(quickSaleConfig.productSearchUrl, window.location.origin);
            url.searchParams.set('query', query || '');
            url.searchParams.set('_ts', Date.now().toString());
            return url.toString();
        },

        async loadFeaturedProducts() {
            this.showingFeatured = true;
            try {
                let res = await fetch(this.productSearchUrl(''), { cache: 'no-store' });
                this.results = await res.json();
            } catch (e) { console.error('Featured products error'); }
        },

        async searchProducts() {
            this.searchResultsOpen = true;
            if (this.search.trim().length < 1) {
                await this.loadFeaturedProducts();
                return;
            }

            this.showingFeatured = false;
            try {
                let res = await fetch(this.productSearchUrl(this.search), { cache: 'no-store' });
                this.results = await res.json();
            } catch (e) { console.error('Search error'); }
        },

        openSearchResults() {
            this.searchResultsOpen = true;
            if (this.search.trim().length > 0) {
                this.searchProducts();
            }
        },

        closeSearchResults() {
            this.searchResultsOpen = false;
        },

        displayPriceLabel(product) {
            const basePrice = Math.round(Number(product?.price ?? 0));
            const piecePrice = Math.round(Number(product?.piece_price ?? 0));
            const isSetProduct = product?.is_splittable == 1 && product?.product_type !== 'fractional';
            const defaultSaleUnit = product?.quick_sale_default_unit === 'piece' ? 'piece' : 'unit';

            if (isSetProduct && defaultSaleUnit === 'piece' && piecePrice > 0) {
                return `حبة ${piecePrice} ر.س`;
            }

            if (isSetProduct) {
                return `طقم ${basePrice} ر.س`;
            }

            return `${basePrice} ر.س`;
        },

        addToCart(product) {
            if (parseFloat(product.quantity) <= 0) {
                Swal.fire({ title: 'عذراً', text: 'هذا المنتج منتهي من المخزون', icon: 'error' });
                return;
            }

            let temp_id = Date.now() + Math.random();
            let basePrice = Math.round(parseFloat(product.price)) || 0;
            let piecePrice = Math.round(product.piece_price) || 0;
            let isSplittableUnit = product.is_splittable == 1 && product.product_type !== 'fractional';
            let preferredUnit = (product.quick_sale_default_unit === 'piece') ? 'piece' : 'unit';
            let defaultSaleUnit = isSplittableUnit ? preferredUnit : 'unit';
            let defaultPrice = isSplittableUnit
                ? (defaultSaleUnit === 'piece' ? piecePrice : basePrice)
                : basePrice;

            this.cart.push({
                temp_id: temp_id,
                product_id: product.id,
                name: product.name,
                is_fractional: product.product_type === 'fractional',
                is_splittable: product.is_splittable == 1,
                items_per_unit: product.items_per_unit || 1,
                piece_price: piecePrice,
                sale_unit: defaultSaleUnit,
                base_price: basePrice,
                price: defaultPrice,
                quantity: 1,
                total: defaultPrice,
                fraction_id: '0',
                is_custom: false,
                custom_name: '',
                custom_consumption: '',
                available_fractions: product.fractions || []
            });

            this.search = '';
            this.searchResultsOpen = false;
            this.loadFeaturedProducts();
            this.$refs.searchInput.focus();
        },

        updateSplittablePrice(item) {
            if (item.sale_unit === 'piece') {
                item.price = item.piece_price;
            } else {
                item.price = item.base_price;
            }
            this.calculateItemTotal(item);
        },

        appendLaborDescription(option) {
            const currentValue = (this.description || '').trim();

            // إذا كان الوصف فارغاً نضع الخيار مباشرة كنص ابتدائي.
            if (!currentValue) {
                this.description = option;
                return;
            }

            // منع تكرار نفس الخيار داخل الوصف عند الضغط عليه أكثر من مرة.
            if (currentValue.includes(option)) {
                return;
            }

            // عند وجود نص سابق: نضيف الخيار الجديد في نهاية الوصف مع فاصل واضح.
            this.description = `${currentValue} - ${option}`;
        },

        updateFractionPrice(item) {
            if (item.fraction_id === 'custom') {
                item.is_custom = true;
                item.price = 0;
                item.custom_consumption = '';
            } else if (item.fraction_id && item.fraction_id !== '0') {
                item.is_custom = false;
                let selected = item.available_fractions.find(f => f.id == item.fraction_id);
                if (selected) { item.price = Math.round(selected.price); }
            } else {
                item.is_custom = false;
                item.price = item.base_price;
            }
            this.calculateItemTotal(item);
        },

        calculateItemTotal(item) {
            item.total = item.quantity * item.price;
        },

        increase(item) { if (item.is_fractional) return; item.quantity++; this.calculateItemTotal(item); },
        decrease(item) { if (item.is_fractional) return; if (item.quantity > 1) { item.quantity--; this.calculateItemTotal(item); } },
        removeItem(item) { this.cart = this.cart.filter(i => i.temp_id !== item.temp_id); },

        addTintItemsToCart(detail) {
            const items = Array.isArray(detail?.items) ? detail.items : [];
            if (!items.length) return;
            this.cart.push(...items);
            this.search = '';
            this.$nextTick(() => this.$refs.searchInput?.focus());
            Swal.fire({
                title: 'تمت الإضافة',
                text: 'أضيفت عملية التضليل إلى سلة البيع.',
                icon: 'success',
                timer: 1200,
                showConfirmButton: false,
            });
        },

        isFirstTintGroupItem(item, index) {
            if (!item.tint_group_id) return false;
            return this.cart.findIndex(candidate => candidate.tint_group_id === item.tint_group_id) === index;
        },

        tintGroupItems(groupId) {
            return this.cart.filter(item => item.tint_group_id === groupId);
        },

        tintGroupTotal(groupId) {
            return this.tintGroupItems(groupId).reduce((sum, item) => sum + Number(item.total || 0), 0);
        },

        tintGroupDetails(groupId) {
            return this.tintGroupItems(groupId)[0]?.tint_group_details || [];
        },

        toggleTintGroupDetails(groupId) {
            this.expandedTintGroups[groupId] = !this.expandedTintGroups[groupId];
        },

        isTintGroupExpanded(groupId) {
            return Boolean(this.expandedTintGroups[groupId]);
        },

        removeTintGroup(groupId) {
            this.cart = this.cart.filter(item => item.tint_group_id !== groupId);
        },

        get items_total() { return this.cart.reduce((sum, item) => sum + (Math.round(item.total) || 0), 0); },
        get tax_value() { return (this.items_total * this.tax_rate) / 100; },
        get base_final_total() { return Math.round(this.items_total + this.tax_value + (Math.round(this.labor_total) || 0)); },
        get final_total() {
            if (this.sale_type === 'credit' && (this.agreed_credit_total || 0) > 0) {
                return Math.round(this.agreed_credit_total);
            }
            return this.base_final_total;
        },
        get remaining() {
            if (this.sale_type === 'credit') {
                return this.final_total;
            }
            const paidForRemaining = this.sale_type === 'mixed' ? this.mixedTotal : this.paid_amount;
            return this.final_total - (Math.round(paidForRemaining) || 0);
        },

        get mixedTotal() {
            return (this.mixedCash || 0) + (this.mixedCard || 0);
        },

        get paidForCoverage() {
            return this.sale_type === 'mixed' ? this.mixedTotal : (this.paid_amount || 0);
        },

        get operationCoverage() {
            return (Math.round(this.paidForCoverage) || 0) + (this.hasPartialCredit ? (Math.round(this.partial_credit_amount) || 0) : 0);
        },

        updateMixedTotal() {
            this.paid_amount = Math.round(this.mixedTotal);
        },

        setPaymentType(type) {
            this.sale_type = type;
            if (type === 'cash' || type === 'card' || type === 'credit') {

                this.mixedCash = '';
                this.mixedCard = '';
            }

            if (type === 'mixed') {
                this.updateMixedTotal();
            }

            if (type === 'credit') {
                this.paid_amount = 0;
                this.hasPartialCredit = false;
                this.partial_credit_amount = 0;
                this.agreed_credit_total = '';
            } else {
                this.agreed_credit_total = '';
                this.partial_credit_amount = 0;
                if (!this.hasPartialCredit) {
                    this.credit_note = '';
                }
            }
        },

        async prepareForm(form) {
            if (this.saleSubmitting) return;
            if (this.pendingQuickSaleOperation?.id && this.pendingQuickSaleOperation.id !== this.clientOperationId) {
                return Swal.fire({
                    title: 'عملية سابقة غير مؤكدة',
                    text: 'افحص حالة العملية المعلقة أو امسح التنبيه قبل اعتماد عملية جديدة حتى لا يحدث تكرار أو ضياع.',
                    icon: 'warning',
                });
            }
            this.ensureClientOperationId();
            if (this.cart.length === 0 && Math.round(this.labor_total) <= 0) {
                return Swal.fire({ title: 'تنبيه', text: 'يرجى إضافة منتج أو أجور يد.', icon: 'warning' });
            }

            if (this.labor_total > 0 && (!this.description || this.description.trim().length < 3)) {
                return Swal.fire({ title: 'تنبيه', text: 'يرجى كتابة وصف العمل في خانة الملاحظات.', icon: 'warning', confirmButtonText: 'حسناً' });
            }

            for (let item of this.cart) {
                if (item.is_fractional && Number(item.quantity) !== 1) {
                    return Swal.fire({ title: 'تنبيه', text: `منتج الرول ${item.name} يباع كسطر مستقل ولا يقبل تغيير الكمية.`, icon: 'warning' });
                }

                if (item.is_fractional && (item.fraction_id === '0' || !item.fraction_id)) {
                    return Swal.fire({ title: 'تنبيه', text: `يرجى اختيار نوع التجزئة لـ ${item.name}`, icon: 'warning' });
                }

                if (item.is_fractional && item.fraction_id === 'custom' && ((Number(item.custom_consumption) || 0) <= 0 || (Number(item.price) || 0) <= 0)) {
                    return Swal.fire({ title: 'تنبيه', text: `القص المخصص لـ ${item.name} يتطلب أمتاراً وسعراً أكبر من صفر.`, icon: 'warning' });
                }
            }

            if (!this.sale_type) {
                return Swal.fire({ title: 'تنبيه', text: 'يرجى اختيار نوع الدفع أولاً.', icon: 'warning', confirmButtonText: 'حسناً' });
            }

            // التحقق من الآجل الكامل
            if (this.sale_type === 'credit' && !this.employee_id) {
                return Swal.fire({ title: 'تنبيه', text: 'يرجى اختيار الموظف للبيع الآجل.', icon: 'warning', confirmButtonText: 'حسناً' });
            }

            const agreedCreditTotal = Math.round(Number(this.agreed_credit_total) || 0);

            if ((this.sale_type === 'credit' || this.hasPartialCredit) && (!this.credit_note || this.credit_note.trim().length < 2)) {
                return Swal.fire({ title: 'تنبيه', text: 'اسم العملية إلزامي عند اختيار آجل كامل أو جزئي.', icon: 'warning', confirmButtonText: 'حسناً' });
            }

            if (this.sale_type === 'credit' && agreedCreditTotal <= 0) {
                return Swal.fire({ title: 'تنبيه', text: 'يرجى إدخال القيمة الآجلة للفاتورة.', icon: 'warning', confirmButtonText: 'حسناً' });
            }

            if (this.sale_type === 'credit' && agreedCreditTotal < this.base_final_total) {
                return Swal.fire({
                    title: 'تنبيه',
                    text: `قيمة الأجل يجب أن تكون مساوية لإجمالي الفاتورة الحالي أو أعلى منه (${Math.round(this.base_final_total)} ريال).`,
                    icon: 'warning',
                    confirmButtonText: 'حسناً'
                });
            }

            // التحقق من الآجل الجزئي
            if (this.hasPartialCredit && !this.employee_id) {
                return Swal.fire({
                    title: 'تنبيه',
                    text: 'يرجى اختيار الموظف لتسجيل المبلغ المتبقي كآجل.',
                    icon: 'warning'
                });
            }

            if (this.hasPartialCredit && (this.sale_type === 'cash' || this.sale_type === 'card' || this.sale_type === 'mixed')) {
                const debtAmount = Math.round(Number(this.partial_credit_amount) || 0);
                const paidAmount = this.sale_type === 'mixed'
                    ? Math.round(Number(this.mixedTotal) || 0)
                    : Math.round(Number(this.paid_amount) || 0);

                if (debtAmount <= 0) {
                    return Swal.fire({ title: 'تنبيه', text: 'يرجى إدخال قيمة الأجل المتبقية على الموظف.', icon: 'warning' });
                }

                if ((paidAmount + debtAmount) < Math.round(this.final_total)) {
                    return Swal.fire({
                        title: 'خطأ في الحساب',
                        text: `مجموع المدفوع (${paidAmount} ريال) + الأجل (${debtAmount} ريال) أقل من إجمالي العملية (${Math.round(this.final_total)} ريال).`,
                        icon: 'error'
                    });
                }
            }

            // التحقق من المبلغ المدفوع حسب نوع البيع
            if (this.sale_type === 'mixed') {
                if (this.mixedTotal < this.final_total && !this.hasPartialCredit) {
                    return Swal.fire({
                        title: 'خطأ في الدفع',
                        text: `إجمالي المدفوع (${this.mixedTotal} ريال) أقل من قيمة الفاتورة (${this.final_total} ريال)`,
                        icon: 'error'
                    });
                }

                // إضافة تفاصيل الدفع المختلط إلى items_json
                let cartData = [...this.cart];
                cartData.push({
                    _temp: true,
                    payment_details: {
                        cash: this.mixedCash,
                        card: this.mixedCard
                    }
                });
                this.updateMixedTotal();
                this.items_json = JSON.stringify(cartData);
            }
            else if ((this.sale_type === 'cash' || this.sale_type === 'card') && !this.hasPartialCredit && this.paid_amount < this.final_total) {
                return Swal.fire({
                    title: 'خطأ في الدفع',
                    text: `المبلغ المدفوع (${Math.round(this.paid_amount)} ريال) أقل من قيمة الفاتورة (${Math.round(this.final_total)} ريال).`,
                    icon: 'error',
                    confirmButtonText: 'تعديل'
                });
            } else {
                this.items_json = JSON.stringify(this.cart);
            }


            if (this.tax_rate > 0 && !this.hasStoreTaxNumber) {
                const result = await Swal.fire({
                    title: 'إخلاء مسؤولية ضريبية',
                    text: 'أنت بصدد فرض ضريبة بينما المتجر لا يملك رقماً ضريبياً مسجلاً. هل تتحمل مسؤولية هذا الإجراء قانونياً؟',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '',
                    cancelButtonColor: '',
                    confirmButtonText: 'نعم، استمرار',
                    cancelButtonText: 'إلغاء'
                });

                if (!result.isConfirmed) return;
            }

            this.saleSubmitting = true;
            this.rememberPendingOperation();
            this.$nextTick(() => form.submit());
        }
    }
}
