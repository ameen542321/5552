// ينقل إرشادات وحدات وكميات تعديل المنتج كما هي دون تغيير حقول المخزون المرسلة.
if (document.querySelector('[data-product-edit-interface]')) {
    let fractionIndex = Number(document.querySelector('[data-product-edit-interface]')?.dataset.fractionIndex || 0);

        function toggleFractionSection() {
            const type = document.getElementById('product_type').value;
            document.getElementById('fractional_product_guidance').classList.toggle('hidden', type !== 'fractional');
            document.getElementById('fractions_section').classList.toggle('hidden', type !== 'fractional');
            document.getElementById('waste_percentage_div').classList.toggle('hidden', type !== 'fractional');
            document.getElementById('roll_length_div').classList.toggle('hidden', type !== 'fractional');
            document.getElementById('splittable_options_div').classList.toggle('hidden', type !== 'standard');
        }

        let setMinStockPreparedForSubmit = false;

        function productUnitMode() {
            const productType = document.getElementById('product_type')?.value || 'standard';
            if (productType === 'fractional') return 'fractional';
            return document.getElementById('is_splittable')?.checked ? 'set' : 'piece';
        }

        function currentItemsPerUnit() {
            const value = Number(document.getElementById('items_per_unit')?.value || 1);
            return Number.isFinite(value) && value > 0 ? value : 1;
        }

        function formatDecimal(value) {
            if (!Number.isFinite(value)) return '';
            return Number((Math.round(value * 100000000) / 100000000).toFixed(8)).toString();
        }

        function formatPiecesAsSet(pieces, itemsPerUnit) {
            const roundedPieces = Math.round(Number(pieces) || 0);
            const items = Math.max(1, Math.round(Number(itemsPerUnit) || 1));
            const sets = Math.floor(roundedPieces / items);
            const remainingPieces = roundedPieces % items;
            const parts = [];

            if (sets > 0) parts.push(`${sets} طقم`);
            if (remainingPieces > 0) parts.push(`${remainingPieces} حبة`);

            return parts.length ? parts.join(' و') : '0 حبة';
        }

        function normalizeVisiblePieceCount() {
            const minStockInput = document.getElementById('min_stock');
            if (!minStockInput || minStockInput.value === '') return;

            const numericValue = Number(minStockInput.value);
            if (Number.isFinite(numericValue)) {
                minStockInput.value = String(Math.max(0, Math.round(numericValue)));
            }
        }

        function convertStoredSetMinStockToPieces() {
            const minStockInput = document.getElementById('min_stock');
            if (!minStockInput || productUnitMode() !== 'set' || minStockInput.dataset.visibleAsPieces === 'true') return;

            const storedSetValue = Number(minStockInput.value || 0);
            if (!Number.isFinite(storedSetValue)) return;

            minStockInput.value = String(Math.max(0, Math.round(storedSetValue * currentItemsPerUnit())));
            minStockInput.dataset.visibleAsPieces = 'true';
        }

        function prepareSetMinStockForSubmit(form) {
            const minStockInput = document.getElementById('min_stock');
            if (!minStockInput || productUnitMode() !== 'set' || setMinStockPreparedForSubmit) return;

            normalizeVisiblePieceCount();
            const pieces = Number(minStockInput.value || 0);
            if (!Number.isFinite(pieces)) return;

            minStockInput.value = formatDecimal(pieces / currentItemsPerUnit());
            minStockInput.dataset.visibleAsPieces = 'false';
            setMinStockPreparedForSubmit = true;
        }

        function updateSetMinStockHint() {
            const minStockInput = document.getElementById('min_stock');
            const minHint = document.getElementById('min_stock_unit_hint');
            if (!minStockInput || !minHint || productUnitMode() !== 'set') return;

            const items = currentItemsPerUnit();
            const pieces = Number(minStockInput.value || 0);
            if (!Number.isFinite(pieces)) return;

            const storedSets = Number(formatDecimal(pieces / items));
            const restoredPieces = storedSets * items;
            const isStorable = Math.abs(restoredPieces - Math.round(pieces)) <= 0.0001;
            const allowedPieces = Array.from({ length: items }, (_, index) => index + 1)
                .filter((candidate) => {
                    const storedCandidate = Number(formatDecimal(candidate / items));
                    return Math.abs((storedCandidate * items) - candidate) <= 0.0001;
                });

            minStockInput.setCustomValidity(isStorable ? '' : 'اختر عدد حبات يمكن حفظه بدقة لهذا الطقم.');
            minHint.classList.toggle('ui-status-danger', !isStorable);
            minHint.classList.toggle('ui-status-warning', isStorable);
            minHint.textContent = isStorable
                ? `أدخل الحد الأدنى بالحبة. القيمة الحالية ${Math.round(pieces)} حبة = ${formatPiecesAsSet(pieces, items)}، وسيُحفظ داخليًا كـ ${formatDecimal(pieces / items)} طقم.`
                : `لا يمكن حفظ ${Math.round(pieces)} حبة بدقة لهذا الطقم. القيم المقبولة داخل الطقم: ${allowedPieces.join('، ')}، وتتكرر بنفس الزيادة بعد ${items}.`;

            return isStorable;
        }

        function handleProductUnitModeChange() {
            if (productUnitMode() === 'set') {
                document.getElementById('min_stock')?.setAttribute('data-visible-as-pieces', 'true');
            }
            updateProductUnitGuidance();
        }

        function updateProductUnitGuidance() {
            const mode = productUnitMode();
            const minStockInput = document.getElementById('min_stock');
            const priceHint = document.getElementById('price_unit_hint');
            const costHint = document.getElementById('cost_unit_hint');
            const minHint = document.getElementById('min_stock_unit_hint');

            let priceText = 'سعر بيع الحبة الواحدة.';
            let costText = 'تكلفة الحبة الواحدة.';
            let minText = 'الحد الأدنى يحسب بالحبة، لذلك يجب أن يكون رقمًا صحيحًا.';
            let minStep = '1';
            let shouldRoundMinStock = true;

            if (mode === 'fractional') {
                priceText = 'سعر بيع الرول الكامل؛ أسعار القص تُدار من خيارات التجزئة.';
                costText = 'تكلفة الرول الكامل، وليست تكلفة المتر الواحد.';
                minText = 'الحد الأدنى يحسب بعدد الرولات. يمكن إدخال كسور مثل 0.50 رول إذا كان التنبيه مطلوبًا قبل اكتمال رول كامل.';
                minStep = '0.01';
                shouldRoundMinStock = false;
                if (minStockInput) minStockInput.dataset.visibleAsPieces = 'false';
            } else if (mode === 'set') {
                priceText = 'سعر بيع الطقم كاملًا، وليس سعر الحبة داخل الطقم.';
                costText = 'تكلفة الطقم كاملًا، وليس تكلفة الحبة داخل الطقم.';
                minText = 'أدخل أدنى حد للكمية بالحبة، وسيعرض النظام ما يعادلها بالطقم.';
                minStep = '1';
                shouldRoundMinStock = true;
                convertStoredSetMinStockToPieces();
            } else if (minStockInput) {
                minStockInput.dataset.visibleAsPieces = 'false';
                minStockInput.setCustomValidity('');
                minHint?.classList.remove('ui-status-danger');
                minHint?.classList.add('ui-status-warning');
            }

            if (priceHint) priceHint.textContent = priceText;
            if (costHint) costHint.textContent = costText;
            if (minHint) minHint.textContent = minText;

            if (minStockInput) {
                minStockInput.step = minStep;
                minStockInput.min = '0';
                if (shouldRoundMinStock) {
                    normalizeVisiblePieceCount();
                }
            }

            updateSetMinStockHint();
        }

        function addFractionRow() {
            // عند إضافة خيار جديد للرول، قيمة الاستهلاك تُدخل بالمتر حتى يخصم البيع نفس الرقم من المخزون.
            const container = document.getElementById('fractions_container');
            const div = document.createElement('div');
            div.className = "ui-product-fraction-row";
            div.innerHTML = `
                <input type="text" name="fractions[${fractionIndex}][option_label]" placeholder="الاسم" required class="ui-product-fraction-main ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                <input type="number" step="0.01" name="fractions[${fractionIndex}][deduction_value]" placeholder="الاستهلاك بالمتر" required class="ui-product-fraction-compact-field ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                <input type="number" step="0.01" name="fractions[${fractionIndex}][price]" placeholder="السعر" required class="ui-product-fraction-compact-field ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                <button type="button" data-product-remove-fraction class="ui-product-fraction-remove ui-status-danger px-2">×</button>
            `;
            container.appendChild(div);
            fractionIndex++;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('product_type')?.addEventListener('change', () => {
                toggleFractionSection();
                handleProductUnitModeChange();
            });
            document.getElementById('is_splittable')?.addEventListener('change', () => {
                handleProductUnitModeChange();
            });
            document.querySelector('[data-product-add-fraction]')?.addEventListener('click', addFractionRow);
            document.getElementById('min_stock')?.addEventListener('input', updateProductUnitGuidance);
            document.getElementById('min_stock')?.addEventListener('blur', updateProductUnitGuidance);
            document.getElementById('items_per_unit')?.addEventListener('input', updateProductUnitGuidance);
            document.getElementById('items_per_unit')?.addEventListener('change', updateProductUnitGuidance);
            document.querySelector('form[action*="products"]')?.addEventListener('submit', function(event) {
                if (productUnitMode() === 'set' && updateSetMinStockHint() === false) {
                    event.preventDefault();
                    document.getElementById('min_stock')?.reportValidity();
                    return;
                }
                prepareSetMinStockForSubmit(this);
            });
            toggleFractionSection();
            updateProductUnitGuidance();
        });
}
