// ينقل منشئ بيع التضليل كما هو؛ المنتجات وحدود المخزون تصل عبر عقد إعداد Blade فقط.
const root = document.querySelector('[data-tint-sale-config]');
if (root) {
    const tintConfig = JSON.parse(root.dataset.tintSaleConfig || '{}');
    window.tintSaleModal = function tintSaleModal(config = tintConfig) {
        return {
            open: false,
            products: [], types: [], sizes: [],
            fullMode: false, selectedWorks: [], workSelections: {}, activeWork: '',
            fullSelections: { front: { type: '', size: '', grade: '' }, rear: { type: '', size: '', grade: '' }, windows: { type: '', size: '', grade: '' } },
            windowCount: 1, customOpen: false, customRows: [], customSequence: 0, finalPrice: 0,
            activeButton: 'ui-border ui-status-info-bg ui-title ui-border',
            idleButton: 'ui-border ui-surface-muted-bg ui-text-muted ui-border ui-surface-muted-bg',
            fullComponents: [
                { id: 'front', work: 'front', shortLabel: 'أمامي', label: 'الأمامي', hint: 'المقاس المعتاد: كبير.', quantity: 1 },
                { id: 'rear', work: 'rear', shortLabel: 'خلفي', label: 'الخلفي', hint: 'المقاس المعتاد: كبير.', quantity: 1 },
                { id: 'windows', work: 'window', shortLabel: 'درايش', label: 'الجوانب والدرايش', hint: 'المقاس المعتاد: صغير.', quantity: 4 },
            ],

            init() {
                this.products = (config.products || []).map(product => this.mapProduct(product)).filter(product => product.stock > 0);
                this.rebuildFilters();
            },
            openModal() { this.open = true; document.documentElement.classList.add('overflow-hidden'); },
            closeModal() { this.open = false; document.documentElement.classList.remove('overflow-hidden'); },
            normalize(value) { return String(value || '').trim().toLowerCase().replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه').replace(/\s+/g, ' '); },
            slug(value) { return this.normalize(value).replace(/\s+/g, '-'); },
            parseIdentity(name) {
                const tokens = String(name || '').trim().replace(/\s+/g, ' ').split(' ').filter(Boolean);
                if (tokens.length < 3) return { type: '', typeId: '', size: '', sizeLabel: '', grade: '' };
                let sizeIndex = tokens.findIndex(token => ['كبير', 'صغير'].includes(token));
                let gradeIndex = tokens.findIndex(token => token === 'شفاف' || /^0?[1-9]\d*$/.test(token));
                if (sizeIndex < 0 || gradeIndex < 0) { sizeIndex = 1; gradeIndex = 2; }
                const type = tokens.slice(0, sizeIndex).join(' ') || tokens[0];
                const sizeLabel = tokens[sizeIndex] || '';
                return { type, typeId: this.slug(type), size: this.slug(sizeLabel), sizeLabel, grade: tokens[gradeIndex] || '' };
            },
            inferWork(label) {
                const value = this.normalize(label);
                if (value.includes('امامي')) return 'front';
                if (value.includes('خلفي')) return 'rear';
                if (value.includes('دريش')) return 'window';
                if (value.includes('كامل')) return 'full';
                return '';
            },
            mapProduct(product) {
                const identity = this.parseIdentity(product.name);
                return {
                    id: String(product.id), name: product.name, type: identity.type, typeId: identity.typeId,
                    size: identity.size, sizeLabel: identity.sizeLabel, grade: identity.grade,
                    price: Number(product.price || 0), stock: Number(product.quantity || 0), waste: Number(product.waste_percentage || 0),
                    fractions: (product.fractions || []).map(fraction => ({ id: String(fraction.id), label: fraction.option_label, work: this.inferWork(fraction.option_label), meters: Number(fraction.deduction_value || 0), price: Number(fraction.price || 0) })),
                };
            },
            rebuildFilters() {
                const typeMap = new Map(), sizeMap = new Map();
                this.products.forEach(product => {
                    if (product.typeId && !typeMap.has(product.typeId)) typeMap.set(product.typeId, { id: product.typeId, label: product.type });
                    if (product.size && !sizeMap.has(product.size)) sizeMap.set(product.size, { id: product.size, label: product.sizeLabel });
                });
                this.types = [...typeMap.values()]; this.sizes = [...sizeMap.values()];
            },
            workLabel(work) { return ({ front: 'أمامي', rear: 'خلفي', window: 'دريشة' })[work] || work; },
            emptySelection() { return { type: '', size: '', grade: '' }; },
            isWorkSelected(work) { return this.selectedWorks.includes(work); },
            selectType(selection, type) { selection.type = type; selection.size = ''; selection.grade = ''; this.syncPrice(); },
            selectSize(selection, size) { selection.size = size; selection.grade = ''; this.syncPrice(); },
            selectGrade(selection, grade) { selection.grade = grade; this.syncPrice(); },
            selectFullWork() {
                this.fullMode = true; this.selectedWorks = []; this.workSelections = {};
                this.fullSelections = { front: this.emptySelection(), rear: this.emptySelection(), windows: this.emptySelection() };
                this.activeWork = 'front';
                this.syncPrice();
            },
            toggleWork(work) {
                this.fullMode = false; this.fullSelections = { front: this.emptySelection(), rear: this.emptySelection(), windows: this.emptySelection() };
                if (this.selectedWorks.includes(work)) {
                    // الضغط على عمل محدد ينقلك إلى حقوله، وزر «إلغاء» داخل البطاقة هو المسؤول عن حذفه.
                    this.activeWork = work;
                } else {
                    this.selectedWorks.push(work);
                    this.workSelections[work] = this.emptySelection();
                    this.activeWork = work;
                }
                this.syncPrice();
            },
            removeWork(work) {
                this.selectedWorks = this.selectedWorks.filter(item => item !== work);
                delete this.workSelections[work];
                this.activeWork = this.selectedWorks[0] || '';
                this.syncPrice();
            },
            isFullComponentComplete(component) {
                const selection = this.fullSelections[component.id];
                return Boolean(selection?.type && selection?.size && selection?.grade);
            },
            advanceFullComponent(componentId) {
                const currentIndex = this.fullComponents.findIndex(component => component.id === componentId);
                const nextComponent = this.fullComponents[currentIndex + 1];
                if (nextComponent) this.activeWork = nextComponent.id;
            },
            advanceSelectedWork(work) {
                const currentIndex = this.selectedWorks.indexOf(work);
                const nextWork = this.selectedWorks[currentIndex + 1];
                if (nextWork) this.activeWork = nextWork;
            },
            productsFor(work, typeId = '', size = '', grade = '') {
                return this.products.filter(product => (!typeId || product.typeId === typeId) && (!size || product.size === size) && (!grade || product.grade === grade) && product.fractions.some(fraction => fraction.work === work));
            },
            availableTypesForWork(work) { const ids = new Set(this.productsFor(work).map(product => product.typeId)); return this.types.filter(type => ids.has(type.id)); },
            sizesFor(work, typeId) { const ids = new Set(this.productsFor(work, typeId).map(product => product.size)); return this.sizes.filter(size => ids.has(size.id)); },
            gradesFor(work, typeId, size) { return [...new Set(this.productsFor(work, typeId, size).map(product => product.grade).filter(Boolean))]; },
            resolvePart(work, selection, quantity, label, owner) {
                if (!selection?.type || !selection?.size || !selection?.grade) return null;
                const product = this.productsFor(work, selection.type, selection.size, selection.grade)[0];
                const fraction = product?.fractions.find(item => item.work === work);
                if (!product || !fraction) return null;
                return { key: `${owner}-${work}`, owner, work, label, quantity, product, fraction, unitPrice: fraction.price, linePrice: fraction.price * quantity };
            },
            get resolvedParts() {
                const parts = [];
                if (this.fullMode) this.fullComponents.forEach(component => { const part = this.resolvePart(component.work, this.fullSelections[component.id], component.quantity, component.label + (component.quantity > 1 ? ` × ${component.quantity}` : ''), `full-${component.id}`); if (part) parts.push(part); });
                else this.selectedWorks.forEach(work => { const quantity = work === 'window' ? this.windowCount : 1; const part = this.resolvePart(work, this.workSelections[work], quantity, this.workLabel(work) + (quantity > 1 ? ` × ${quantity}` : ''), `work-${work}`); if (part) parts.push(part); });
                this.customRows.forEach(row => {
                    const product = this.products.find(item => item.id === String(row.productId));
                    if (product && row.name && Number(row.meters) > 0 && Number(row.price) > 0) parts.push({ key: `custom-${row.id}`, owner: 'custom', work: 'custom', label: row.name, quantity: 1, product, fraction: null, customMeters: Number(row.meters), unitPrice: Number(row.price), linePrice: Number(row.price), sourceFractionLabel: row.sourceFractionLabel || 'إدخال يدوي' });
                });
                return parts;
            },
            requiredMeters(part) { const base = part.work === 'custom' ? Number(part.customMeters || 0) : Number(part.fraction?.meters || 0) * Number(part.quantity || 1); return base * (1 + Number(part.product.waste || 0) / 100); },
            selectionPart(work, selection, quantity) { return this.resolvePart(work, selection, quantity, this.workLabel(work), 'stock'); },
            selectionHasStock(work, selection, quantity) { const part = this.selectionPart(work, selection, quantity); return !part || this.requiredMeters(part) <= part.product.stock + 0.0001; },
            selectionStockMessage(work, selection, quantity) {
                const part = this.selectionPart(work, selection, quantity); if (!part) return '';
                const required = this.requiredMeters(part), available = part.product.stock;
                return required <= available + 0.0001 ? `متوفر: يحتاج ${required.toFixed(2)}م من ${available.toFixed(2)}م.` : `الكمية لا تكفي: يحتاج ${required.toFixed(2)}م والمتوفر ${available.toFixed(2)}م.`;
            },
            recordedPrice() { return this.resolvedParts.reduce((sum, part) => sum + part.linePrice, 0); },
            syncPrice() { this.$nextTick(() => { this.finalPrice = Number(this.recordedPrice().toFixed(2)); }); },
            toggleCustomPanel() { this.customOpen = !this.customOpen; if (this.customOpen && !this.customRows.length) this.addCustomRow(); },
            addCustomRow() { this.customRows.push({ id: ++this.customSequence, productId: '', fractionId: '', sourceFractionLabel: '', name: '', meters: '', price: '' }); },
            removeCustomRow(id) { this.customRows = this.customRows.filter(row => row.id !== id); this.syncPrice(); },
            selectCustomProduct(row, productId) { row.productId = productId; row.fractionId = ''; row.sourceFractionLabel = ''; row.name = ''; row.meters = ''; row.price = ''; this.syncPrice(); },
            customProduct(row) { return this.products.find(product => product.id === String(row.productId)); },
            customProductFractions(row) { return this.customProduct(row)?.fractions.filter(fraction => fraction.meters > 0) || []; },
            selectCustomFraction(row, fraction) { row.fractionId = fraction.id; row.sourceFractionLabel = fraction.label; row.name = fraction.label; row.meters = fraction.meters; row.price = fraction.price; this.syncPrice(); },
            customHasStock(row) { const product = this.customProduct(row); if (!product || Number(row.meters) <= 0) return true; return Number(row.meters) * (1 + product.waste / 100) <= product.stock + 0.0001; },
            customStockMessage(row) { const product = this.customProduct(row); if (!product || Number(row.meters) <= 0) return ''; const required = Number(row.meters) * (1 + product.waste / 100); return this.customHasStock(row) ? `متوفر: يحتاج ${required.toFixed(2)}م من ${product.stock.toFixed(2)}م.` : `الكمية لا تكفي: يحتاج ${required.toFixed(2)}م والمتوفر ${product.stock.toFixed(2)}م.`; },
            partDisplayRegistration(part) { return part.work === 'custom' ? `مخصص — ${part.sourceFractionLabel} — ${part.customMeters}م` : `${part.fraction.label} — ${part.fraction.meters}م${part.quantity > 1 ? ` × ${part.quantity}` : ''}`; },
            resetBuilder() { this.fullMode = false; this.selectedWorks = []; this.workSelections = {}; this.activeWork = ''; this.fullSelections = { front: this.emptySelection(), rear: this.emptySelection(), windows: this.emptySelection() }; this.windowCount = 1; this.customOpen = false; this.customRows = []; this.finalPrice = 0; },
            money(value) { return Number(value || 0).toFixed(2) + ' ر.س'; },
            distributeFinalPrice(parts) {
                const target = Number(this.finalPrice || 0), recorded = parts.reduce((sum, part) => sum + part.unitPrice, 0); let remaining = target;
                return parts.map((part, index) => { const price = index === parts.length - 1 ? Number(remaining.toFixed(2)) : Number((recorded > 0 ? target * (part.unitPrice / recorded) : target / parts.length).toFixed(2)); remaining -= price; return { ...part, distributedPrice: price }; });
            },
            groupTitle() {
                if (this.fullMode) return 'تضليل كامل';
                const labels = this.resolvedParts.map(part => part.label);
                return labels.length ? `تضليل — ${labels.join(' + ')}` : 'تضليل';
            },
            groupDetails() {
                return this.resolvedParts.map(part => ({
                    key: part.key, label: part.label, product: part.product.name,
                    registration: this.partDisplayRegistration(part), price: Number(part.linePrice || 0),
                }));
            },
            buildCartItems() {
                const expanded = [];
                this.resolvedParts.forEach(part => { const count = part.work === 'custom' ? 1 : part.quantity; for (let index = 0; index < count; index++) expanded.push({ ...part, unitPrice: part.work === 'custom' ? part.unitPrice : part.fraction.price, componentIndex: index + 1 }); });
                const pricedParts = this.distributeFinalPrice(expanded), groupId = `tint-${Date.now()}-${Math.random().toString(16).slice(2)}`, title = this.groupTitle(), details = this.groupDetails();
                return pricedParts.map((part, index) => ({
                    temp_id: `${groupId}-${index}`, product_id: Number(part.product.id), name: part.product.name,
                    is_fractional: true, is_splittable: false, items_per_unit: 1, piece_price: 0, sale_unit: 'unit', base_price: Number(part.product.price || 0),
                    price: part.distributedPrice, quantity: 1, total: part.distributedPrice,
                    fraction_id: part.work === 'custom' ? 'custom' : part.fraction.id,
                    is_custom: part.work === 'custom', custom_name: part.work === 'custom' ? part.label : '', custom_consumption: part.work === 'custom' ? part.customMeters : '',
                    available_fractions: part.product.fractions.map(fraction => ({ id: fraction.id, option_label: fraction.label, deduction_value: fraction.meters, price: fraction.price })),
                    tint_group_id: groupId, tint_group_label: title, tint_group_details: details,
                    tint_component_label: part.label + (part.quantity > 1 ? ` (${part.componentIndex}/${part.quantity})` : ''),
                }));
            },
            stockErrors(parts) {
                const requiredByProduct = new Map();
                parts.forEach(part => requiredByProduct.set(part.product.id, (requiredByProduct.get(part.product.id) || 0) + this.requiredMeters(part)));
                return [...requiredByProduct.entries()].flatMap(([productId, required]) => { const product = this.products.find(item => item.id === productId); return product && required > product.stock + 0.0001 ? [`${product.name}: المطلوب ${required.toFixed(2)}م والمتوفر ${product.stock.toFixed(2)}م.`] : []; });
            },
            addToQuickSaleCart() {
                const parts = this.resolvedParts, expectedCount = this.fullMode ? this.fullComponents.length : this.selectedWorks.length, standardCount = parts.filter(part => part.work !== 'custom').length;
                if (!parts.length) return Swal.fire({ title: 'تنبيه', text: 'أكمل اختيار عمل واحد على الأقل.', icon: 'warning' });
                if (standardCount < expectedCount) return Swal.fire({ title: 'تنبيه', text: 'أكمل نوع التضليل والحجم والدرجة لجميع الأعمال المحددة.', icon: 'warning' });
                if (Number(this.finalPrice || 0) <= 0) return Swal.fire({ title: 'تنبيه', text: 'سعر العملية النهائي يجب أن يكون أكبر من صفر.', icon: 'warning' });
                const errors = this.stockErrors(parts); if (errors.length) return Swal.fire({ title: 'المخزون غير كافٍ', html: errors.join('<br>'), icon: 'error' });
                const items = this.buildCartItems(); this.$dispatch('tint-items-ready', { items, groupId: items[0]?.tint_group_id }); this.closeModal(); this.resetBuilder();
            },
        };
    }
}
