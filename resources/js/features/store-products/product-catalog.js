const catalogRootSelector = '[data-store-products-catalog]';

const catalogConfig = () => JSON.parse(
    document.querySelector('[data-store-products-catalog-config]')?.textContent || '{}',
);

const normalizeArabicSearch = (value) => String(value || '').toLowerCase().trim()
    .replace(/[\u064B-\u065F\u0670]/g, '').replace(/ـ/g, '')
    .replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ئ/g, 'ي').replace(/ؤ/g, 'و').replace(/ة/g, 'ه');

const fuzzyIncludes = (text, query) => {
    let queryIndex = 0;
    for (const character of text) {
        if (character === query[queryIndex]) queryIndex += 1;
        if (queryIndex === query.length) return true;
    }
    return false;
};

const toggleProductDetails = (detailsId, arrowId, forceOpen = false) => {
    const details = document.getElementById(detailsId);
    const arrow = document.getElementById(arrowId);
    if (!details) return;

    const shouldOpen = forceOpen || details.classList.contains('hidden');
    details.classList.toggle('hidden', !shouldOpen);
    arrow?.classList.toggle('rotate-180', shouldOpen);
};

const appendTextElement = (parent, tagName, className, text) => {
    const element = document.createElement(tagName);
    element.className = className;
    element.textContent = text;
    parent.appendChild(element);
    return element;
};

const appendPriceSummary = (container, label, value, valueClass) => {
    const card = document.createElement('div');
    card.className = 'ui-card p-3';
    appendTextElement(card, 'p', 'ui-text-muted ui-text-caption', label);
    appendTextElement(card, 'p', `${valueClass} font-bold`, value);
    container.appendChild(card);
};

const appendPriceChange = (cell, previousValue, currentValue, currentClass) => {
    appendTextElement(cell, 'span', 'ui-text-muted', previousValue ?? '--');
    cell.append(' ');
    appendTextElement(cell, 'span', 'ui-text-muted', '←');
    cell.append(' ');
    appendTextElement(cell, 'span', `${currentClass} font-bold`, currentValue ?? '--');
};

const openPriceHistoryModal = async (url) => {
    const modal = document.getElementById('priceHistoryModal');
    const loading = document.getElementById('priceHistoryLoading');
    const empty = document.getElementById('priceHistoryEmpty');
    const rows = document.getElementById('priceHistoryRows');
    const productName = document.getElementById('priceHistoryProductName');
    const current = document.getElementById('priceHistoryCurrent');

    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    empty.classList.add('hidden');
    rows.replaceChildren();
    current.replaceChildren();
    productName.textContent = '--';

    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('تعذر تحميل سجل الأسعار');
        const data = await response.json();
        productName.textContent = data.product?.name || '--';
        appendPriceSummary(current, 'سعر البيع الحالي', `${data.product?.price || '0.00'} ر.س`, 'ui-status-info');
        appendPriceSummary(current, 'سعر التكلفة الحالي', `${data.product?.cost_price || '0.00'} ر.س`, 'ui-status-success');
        appendPriceSummary(current, 'آخر تعديل', data.product?.updated_at || '--', 'ui-text-muted');

        if (!data.history?.length) {
            empty.classList.remove('hidden');
            return;
        }

        data.history.forEach((historyItem) => {
            const row = document.createElement('tr');
            const dateCell = document.createElement('td');
            dateCell.className = 'py-3 px-2 ui-text-muted';
            dateCell.dir = 'ltr';
            dateCell.append(document.createTextNode(historyItem.date || '--'));
            appendTextElement(dateCell, 'div', 'ui-text-caption ui-text-muted', historyItem.time || '');
            row.appendChild(dateCell);

            const priceCell = document.createElement('td');
            priceCell.className = 'py-3 px-2';
            appendPriceChange(priceCell, historyItem.old_price, historyItem.new_price, 'ui-status-info');
            row.appendChild(priceCell);

            const costCell = document.createElement('td');
            costCell.className = 'py-3 px-2';
            appendPriceChange(costCell, historyItem.old_cost_price, historyItem.new_cost_price, 'ui-status-success');
            if (historyItem.latest_receipt_unit_cost) {
                appendTextElement(costCell, 'div', 'ui-text-caption ui-status-info mt-1', `سعر التوريد: ${historyItem.latest_receipt_unit_cost}`);
            }
            row.appendChild(costCell);
            appendTextElement(row, 'td', 'py-3 px-2 ui-text-muted', historyItem.actor || 'نظام');
            rows.appendChild(row);
        });
    } catch (error) {
        empty.textContent = error.message || 'تعذر تحميل سجل الأسعار';
        empty.classList.remove('hidden');
    } finally {
        loading.classList.add('hidden');
    }
};

document.addEventListener('alpine:init', () => {
    if (!document.querySelector(catalogRootSelector)) return;
    const config = catalogConfig();
    window.Alpine.data('storeProductsLookup', () => ({
        searchQuery: config.searchQuery || '',
        showAllMatches: false,
        hasServerResults: Boolean(config.hasServerResults),
        products: config.products || [],
        filteredProducts: [],
        init() {
            this.filterClientProducts();
            this.$nextTick(() => {
                if (config.highlightedProductId > 0) this.openProductCard({ id: config.highlightedProductId });
            });
        },
        filterClientProducts() {
            const query = normalizeArabicSearch(this.searchQuery);
            this.showAllMatches = false;
            if (!query) { this.filteredProducts = this.products; return; }
            this.filteredProducts = this.products.filter((product) => {
                const name = normalizeArabicSearch(product.name);
                return name.includes(query)
                    || (query.length >= 3 && fuzzyIncludes(name, query))
                    || normalizeArabicSearch(product.barcode).includes(query)
                    || normalizeArabicSearch(product.description).includes(query);
            });
        },
        visibleProducts() {
            if (this.searchQuery) return this.filteredProducts;
            return this.showAllMatches ? this.filteredProducts : this.filteredProducts.slice(0, 5);
        },
        openProductCard(product) {
            const productId = Number(product?.id || product || 0);
            const card = document.getElementById(`product-card-${productId}`);
            if (!card && product?.card_url) { window.location.href = product.card_url; return; }
            if (!card) return;
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            toggleProductDetails(`details_${productId}`, `arrow_${productId}`, true);
            card.classList.add('ui-border');
            setTimeout(() => card.classList.remove('ui-border'), 2200);
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const catalogRoot = document.querySelector(catalogRootSelector);
    if (!catalogRoot) return;

    catalogRoot.addEventListener('click', (event) => {
        const detailsToggle = event.target.closest('[data-product-details-toggle]');
        if (detailsToggle) toggleProductDetails(detailsToggle.dataset.detailsId, detailsToggle.dataset.arrowId);
        const priceHistoryButton = event.target.closest('[data-price-history-url]');
        if (priceHistoryButton) openPriceHistoryModal(priceHistoryButton.dataset.priceHistoryUrl);
    });
    document.querySelector('[data-price-history-close]')?.addEventListener('click', () => {
        document.getElementById('priceHistoryModal')?.classList.add('hidden');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            event.preventDefault();
            document.getElementById('searchInput')?.focus();
        }
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[id^="details_"]').forEach((details) => details.classList.add('hidden'));
        document.querySelectorAll('[id^="arrow_"]').forEach((arrow) => arrow.classList.remove('rotate-180'));
        document.getElementById('priceHistoryModal')?.classList.add('hidden');
    });
});
