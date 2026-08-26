// ينقل واجهة الاستهلاك الداخلي كما هي؛ المنتجات والكميات تأتي من عقد Blade ولا تتغير حقول الطلب.
const root = document.querySelector('[data-internal-use-config]');
if (root) {
    const config = JSON.parse(root.dataset.internalUseConfig || '{}');
    window.internalUseForm = function internalUseForm() {
        return {
            // المتغيرات الأساسية
            productId: '',
            selectedProduct: null,
            quantity: 0,
            unitType: 'default',
            internalNotes: '',
            totalCost: 0,

            // متغيرات البحث
            searchQuery: '',
            products: config.products || [],

            get filteredProducts() {
                const q = (this.searchQuery || '').toLowerCase().trim();
                const mapped = (this.products || []).map((p) => {
                    const stock = parseFloat(p.quantity) || 0;
                    let stockLabel = `المخزون: ${Math.floor(stock)} قطعة`;

                    if (p.product_type === 'fractional') {
                        stockLabel = `المخزون: ${stock.toFixed(2)} متر`;
                    } else if (p.is_splittable) {
                        const fullKits = Math.floor(stock);
                        const partialKit = stock - fullKits;
                        const pieces = Math.round(partialKit * (parseInt(p.items_per_unit) || 1));
                        stockLabel = pieces > 0
                            ? `المخزون: ${fullKits} طقم + ${pieces} حبة`
                            : `المخزون: ${fullKits} طقم`;
                    }

                    return {
                        id: String(p.id),
                        name: p.name || '',
                        description: p.description || '',
                        barcode: p.barcode || '',
                        stock,
                        productType: p.product_type || 'normal',
                        isSplittable: !!p.is_splittable,
                        itemsPerUnit: parseInt(p.items_per_unit) || 1,
                        rollLength: parseFloat(p.roll_length) || 0,
                        wastePercentage: parseFloat(p.waste_percentage) || 0,
                        stock_label: stockLabel,
                    };
                });

                if (!q) return mapped;

                const normalizedQuery = this.normalizeArabicSearch(q);

                return mapped.filter((p) =>
                    this.normalizeArabicSearch(p.name).includes(normalizedQuery)
                        || this.normalizeArabicSearch(p.description).includes(normalizedQuery)
                        || (p.barcode || '').toLowerCase().includes(q)
                );
            },

            // دالة التهيئة
            init() {
                // تحميل الحالة الافتراضية فقط
            },

            // مسح البحث
            clearSearch() {
                this.searchQuery = '';
            },

            // فلترة المنتجات (تحديث العرض فقط)
            filterProducts() {
                // getter filteredProducts يتولى ذلك تلقائيًا
            },

            selectProduct(id) {
                this.productId = String(id);
                this.updateProduct();
            },

            // تحديث المنتج المحدد
            updateProduct() {
                const product = this.filteredProducts.find((p) => String(p.id) === String(this.productId))
                    || (this.products || []).map((p) => ({
                        id: String(p.id),
                        name: p.name || '',
                        description: p.description || '',
                        stock: parseFloat(p.quantity) || 0,
                        barcode: p.barcode || '',
                        productType: p.product_type || 'normal',
                        isSplittable: !!p.is_splittable,
                        itemsPerUnit: parseInt(p.items_per_unit) || 1,
                        rollLength: parseFloat(p.roll_length) || 0,
                        wastePercentage: parseFloat(p.waste_percentage) || 0,
                    })).find((p) => String(p.id) === String(this.productId));

                if (this.productId && product) {
                    this.selectedProduct = product;

                    // تعيين نوع الوحدة الافتراضي
                    if (this.selectedProduct.productType === 'fractional') {
                        this.unitType = 'roll';
                    } else if (this.selectedProduct.isSplittable) {
                        this.unitType = 'kit';
                    } else {
                        this.unitType = 'default';
                    }

                    this.quantity = 0;
                } else {
                    this.selectedProduct = null;
                    this.unitType = 'default';
                    this.quantity = 0;
                }
            },

            normalizeArabicSearch(value) {
                return String(value || '')
                    .toLowerCase()
                    .replace(/[أإآ]/g, 'ا')
                    .replace(/ى/g, 'ي')
                    .replace(/ئ/g, 'ي')
                    .replace(/ؤ/g, 'و')
                    .replace(/ة/g, 'ه')
                    .replace(/\s+/g, ' ')
                    .trim();
            },

            // باقي الدوال كما هي من الكود السابق...
            getDetailedStockDisplay() {
                if (!this.selectedProduct) return '';

                if (this.selectedProduct.productType === 'fractional') {
                    let meters = this.selectedProduct.stock;
                    let rolls = meters / this.selectedProduct.rollLength;
                    let fullRolls = Math.floor(rolls);
                    let remainingMeters = meters - (fullRolls * this.selectedProduct.rollLength);

                    return `
                        <div>${meters.toFixed(2)} متر</div>
                        <div class="ui-text-caption ui-text-muted">( ${fullRolls} رول كامل + ${remainingMeters.toFixed(2)} متر )</div>
                    `;
                }
                else if (this.selectedProduct.isSplittable) {
                    let fullKits = Math.floor(this.selectedProduct.stock);
                    let remainingPart = this.selectedProduct.stock - fullKits;
                    let remainingPieces = Math.round(remainingPart * this.selectedProduct.itemsPerUnit * 100) / 100;

                    let display = `<div>${fullKits} طقم`;
                    if (remainingPieces > 0) {
                        display += ` <span class="ui-status-warning">+ ${remainingPieces} حبة</span>`;
                    }
                    display += `</div>`;
                    display += `<div class="ui-text-caption ui-text-muted">(إجمالي: ${(fullKits + remainingPart).toFixed(3)} طقم)</div>`;

                    return display;
                }
                else {
                    return `<div>${Math.floor(this.selectedProduct.stock)} قطعة</div>`;
                }
            },

            setMeterQuantity(value) {
                this.quantity = value;
                this.validateMeterQuantity();
            },

            setRollQuantity(value) {
                this.quantity = value;
                this.validateRollQuantity();
            },

            setKitQuantity(value) {
                this.quantity = value;
                this.validateKitQuantity();
            },

            setPieceQuantity(value) {
                this.quantity = value;
                this.validatePieceQuantity();
            },

            setNormalQuantity(value) {
                this.quantity = value;
                this.validateNormalQuantity();
            },

            validateMeterQuantity() {
                let value = parseFloat(this.quantity);
                if (isNaN(value) || value < 0.01) value = 0.01;
                if (value > this.getMaxValue()) value = this.getMaxValue();
                this.quantity = Math.round(value * 100) / 100;
            },

            validateRollQuantity() {
                let value = parseFloat(this.quantity);
                if (isNaN(value) || value < 0.001) value = 0.001;
                if (value > this.getMaxValue()) value = this.getMaxValue();
                this.quantity = Math.round(value * 1000) / 1000;
            },

            validateKitQuantity() {
                let value = Math.floor(parseFloat(this.quantity));
                if (isNaN(value) || value < 1) value = 1;
                if (value > this.getMaxValue()) value = Math.floor(this.getMaxValue());
                this.quantity = value;
            },

            validatePieceQuantity() {
                let value = Math.floor(parseFloat(this.quantity));
                if (isNaN(value) || value < 1) value = 1;
                if (value > this.getMaxValue()) value = Math.floor(this.getMaxValue());
                this.quantity = value;
            },

            validateNormalQuantity() {
                let value = Math.floor(parseFloat(this.quantity));
                if (isNaN(value) || value < 1) value = 1;
                let maxValue = Math.floor(this.selectedProduct.stock);
                if (value > maxValue) value = maxValue;
                this.quantity = value;
            },

            getDeductionDetails() {
                if (!this.selectedProduct || this.quantity <= 0) return '';

                if (this.unitType === 'meters') {
                    return `${this.quantity.toFixed(2)} متر من إجمالي ${this.selectedProduct.stock.toFixed(2)} متر`;
                }
                else if (this.unitType === 'roll') {
                    let meters = this.quantity * this.selectedProduct.rollLength;
                    return `${this.quantity.toFixed(3)} رول (ما يعادل ${meters.toFixed(2)} متر)`;
                }
                else if (this.unitType === 'piece') {
                    let kits = this.quantity / this.selectedProduct.itemsPerUnit;
                    return `${this.quantity} حبة (ما يعادل ${kits.toFixed(3)} طقم)`;
                }
                else if (this.unitType === 'kit') {
                    return `${this.quantity} طقم`;
                }
                else if (!this.selectedProduct.isSplittable && this.selectedProduct.productType !== 'fractional') {
                    return `${this.quantity} قطعة`;
                }
                return '';
            },

            getMaxValue() {
                if (!this.selectedProduct || !this.productId) return 0;

                if (this.unitType === 'meters') {
                    return this.selectedProduct.stock;
                } else if (this.unitType === 'roll') {
                    return this.selectedProduct.stock / this.selectedProduct.rollLength;
                } else if (this.unitType === 'piece') {
                    return Math.floor(this.selectedProduct.stock * this.selectedProduct.itemsPerUnit);
                } else if (this.unitType === 'kit') {
                    return Math.floor(this.selectedProduct.stock);
                } else if (!this.selectedProduct.isSplittable && this.selectedProduct.productType !== 'fractional') {
                    return Math.floor(this.selectedProduct.stock);
                }
                return 0;
            },

            getAvailableStockDisplay() {
                if (!this.selectedProduct || !this.productId) return '0';

                if (this.unitType === 'meters') {
                    return `<span class="ui-title">${this.selectedProduct.stock.toFixed(2)} م</span>`;
                } else if (this.unitType === 'roll') {
                    let rolls = this.selectedProduct.stock / this.selectedProduct.rollLength;
                    return `<span class="ui-title">${rolls.toFixed(2)} رول</span> <span class="ui-text-muted">(${this.selectedProduct.stock.toFixed(2)} م)</span>`;
                } else if (this.unitType === 'piece') {
                    let pieces = Math.floor(this.selectedProduct.stock * this.selectedProduct.itemsPerUnit);
                    return `<span class="ui-title">${pieces} حبة</span> <span class="ui-text-muted">(${this.selectedProduct.stock.toFixed(2)} طقم)</span>`;
                } else if (this.unitType === 'kit') {
                    return `<span class="ui-title">${Math.floor(this.selectedProduct.stock)} طقم</span> <span class="ui-text-muted">(+ ${(this.selectedProduct.stock % 1).toFixed(2)} طقم كسور)</span>`;
                } else if (!this.selectedProduct.isSplittable && this.selectedProduct.productType !== 'fractional') {
                    return `<span class="ui-title">${Math.floor(this.selectedProduct.stock)} قطعة</span>`;
                }
                return '0';
            },

            canSubmit() {
                return this.productId &&
                       this.selectedProduct &&
                       this.selectedProduct.stock > 0 &&
                       this.quantity > 0 &&
                       this.quantity <= this.getMaxValue();
            }
        }
    }
}
