document.addEventListener('alpine:init', () => {
    window.Alpine.data('storeTransferBuilder', (products, oldItems) => ({
        products,
        nextKey: 1,
        items: [],
        init() {
            const initialItems = oldItems.length ? oldItems : [{}];
            this.items = initialItems.map((item) => this.makeItem(item));
        },
        makeItem(item = {}) {
            const selectedProduct = this.products.find(
                (product) => String(product.id) === String(item.sender_product_id || ''),
            );
            return {
                key: this.nextKey++,
                sender_product_id: selectedProduct?.id || '',
                query: selectedProduct?.name || '',
                quantity: item.quantity || '',
                unit_type: item.unit_type || 'unit',
                product_type: selectedProduct?.product_type || '',
                is_splittable: Boolean(selectedProduct?.is_splittable),
                open: false,
            };
        },
        addItem() {
            this.items.push(this.makeItem());
        },
        removeItem(index) {
            if (this.items.length > 1) this.items.splice(index, 1);
        },
        filteredProducts(item) {
            const searchTerm = item.query.trim().toLowerCase();
            const selectedProductIds = this.items
                .map((productItem) => String(productItem.sender_product_id))
                .filter(Boolean);
            return this.products.filter((product) => {
                const belongsToCurrentRow = String(product.id) === String(item.sender_product_id);
                const isAvailable = belongsToCurrentRow || !selectedProductIds.includes(String(product.id));
                const matchesSearch = !searchTerm || product.name.toLowerCase().includes(searchTerm);
                return isAvailable && matchesSearch;
            });
        },
        selectProduct(item, product) {
            item.sender_product_id = product.id;
            item.query = product.name;
            item.product_type = product.product_type || '';
            item.is_splittable = Boolean(product.is_splittable);
            item.unit_type = item.product_type === 'fractional'
                ? 'roll'
                : (item.is_splittable ? 'kit' : 'unit');
            item.open = false;
        },
        unitsFor(item) {
            if (item.product_type === 'fractional') {
                return [{ value: 'roll', label: 'رول كامل' }, { value: 'meter', label: 'متر' }];
            }
            if (item.is_splittable) {
                return [{ value: 'kit', label: 'طقم كامل' }, { value: 'piece', label: 'حبة' }];
            }
            return [{ value: 'unit', label: 'حبة / وحدة' }];
        },
    }));
});

const closeProductPicker = (productPicker) => {
    productPicker.querySelector('[data-picker-options]')?.classList.add('hidden');
};

const filterProductPicker = (productPicker) => {
    const pickerInput = productPicker.querySelector('[data-picker-input]');
    const pickerOptions = [...productPicker.querySelectorAll('[data-picker-option]')];
    const searchTerm = (pickerInput?.value || '').trim().toLowerCase();
    let visibleOptionCount = 0;

    pickerOptions.forEach((pickerOption) => {
        const searchableText = (pickerOption.dataset.search || pickerOption.dataset.label || '').toLowerCase();
        const matchesSearch = !searchTerm || searchableText.includes(searchTerm);
        pickerOption.classList.toggle('hidden', !matchesSearch);
        if (matchesSearch) visibleOptionCount += 1;
    });

    productPicker.querySelector('[data-picker-empty]')?.classList.toggle('hidden', visibleOptionCount > 0);
    productPicker.querySelector('[data-picker-options]')?.classList.remove('hidden');
};

document.addEventListener('focusin', (event) => {
    const pickerInput = event.target.closest('[data-transfer-product-picker] [data-picker-input]');
    if (pickerInput) filterProductPicker(pickerInput.closest('[data-transfer-product-picker]'));
});

document.addEventListener('input', (event) => {
    const pickerInput = event.target.closest('[data-transfer-product-picker] [data-picker-input]');
    if (!pickerInput) return;

    const productPicker = pickerInput.closest('[data-transfer-product-picker]');
    const hiddenInput = document.getElementById(productPicker.dataset.hiddenInput);
    if (hiddenInput) hiddenInput.value = '';
    filterProductPicker(productPicker);
});

document.addEventListener('click', (event) => {
    const selectedOption = event.target.closest('[data-transfer-product-picker] [data-picker-option]');
    if (selectedOption) {
        const productPicker = selectedOption.closest('[data-transfer-product-picker]');
        const pickerInput = productPicker.querySelector('[data-picker-input]');
        const hiddenInput = document.getElementById(productPicker.dataset.hiddenInput);
        if (pickerInput && hiddenInput) {
            pickerInput.value = selectedOption.dataset.label;
            hiddenInput.value = selectedOption.dataset.id;
            closeProductPicker(productPicker);
        }
        return;
    }

    document.querySelectorAll('[data-transfer-product-picker]').forEach((productPicker) => {
        if (!productPicker.contains(event.target)) closeProductPicker(productPicker);
    });
});
