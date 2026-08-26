// ينقل إرشادات وحدات وكميات إنشاء المنتج كما هي دون تغيير حقول المخزون المرسلة.
if (document.querySelector('[data-product-create-interface]')) {
    let fractionIndex = 0;

        function toggleFractionSection() {
            const type = document.getElementById('product_type').value;
            document.getElementById('fractional_product_guidance').classList.toggle('hidden', type !== 'fractional');
            const splittableDiv = document.getElementById('splittable_options_div');
            const rollDiv = document.getElementById('roll_length_div');
            const fractionSec = document.getElementById('fractions_section');
            const wasteDiv = document.getElementById('waste_percentage_div');
            const stdQty = document.getElementById('standard_quantity_div');
            const fracQty = document.getElementById('fractional_quantity_div');

            if (type === 'fractional') {
                splittableDiv.classList.add('hidden');
                rollDiv.classList.remove('hidden');
                fractionSec.classList.remove('hidden');
                wasteDiv.classList.remove('hidden');
                stdQty.classList.add('hidden');
                fracQty.classList.remove('hidden');

                // إضافة سطر تلقائي إذا كان فارغاً
                if (document.getElementById('fractions_container').children.length === 0) addFractionRow();
            } else {
                splittableDiv.classList.remove('hidden');
                rollDiv.classList.add('hidden');
                fractionSec.classList.add('hidden');
                wasteDiv.classList.add('hidden');
                stdQty.classList.remove('hidden');
                fracQty.classList.add('hidden');
            }
        }

        function createProductUnitMode() {
            if (document.getElementById('product_type')?.value === 'fractional') return 'fractional';
            return document.getElementById('is_splittable')?.checked ? 'set' : 'piece';
        }

        function createItemsPerUnit() {
            const value = Number(document.getElementById('items_per_unit')?.value || 1);
            return Number.isFinite(value) && value > 0 ? Math.round(value) : 1;
        }

        function createFormatDecimal(value) {
            return Number((Math.round(Number(value || 0) * 100000000) / 100000000).toFixed(8)).toString();
        }

        function createAllowedSetMinimums(itemsPerUnit) {
            return Array.from({ length: itemsPerUnit }, (_, index) => index + 1)
                .filter((pieces) => Math.abs((Number(createFormatDecimal(pieces / itemsPerUnit)) * itemsPerUnit) - pieces) <= 0.0001);
        }

        function validateCreateSetMinimum() {
            const minInput = document.getElementById('min_stock');
            const hint = document.getElementById('create_min_stock_unit_hint');
            if (!minInput || !hint || createProductUnitMode() !== 'set' || minInput.value === '') {
                minInput?.setCustomValidity('');
                return true;
            }

            const pieces = Math.max(0, Math.round(Number(minInput.value || 0)));
            const items = createItemsPerUnit();
            const storedSets = Number(createFormatDecimal(pieces / items));
            const isStorable = Math.abs((storedSets * items) - pieces) <= 0.0001;
            minInput.setCustomValidity(isStorable ? '' : 'اختر عدد حبات يمكن حفظه بدقة لهذا الطقم.');
            hint.classList.toggle('ui-status-danger', !isStorable);
            hint.classList.toggle('ui-status-warning', isStorable);
            hint.textContent = isStorable
                ? `${pieces} حبة = ${createFormatDecimal(pieces / items)} طقم، وسيُحفظ بهذا المقدار.`
                : `لا يمكن حفظ ${pieces} حبة بدقة لهذا الطقم. القيم المقبولة داخل الطقم: ${createAllowedSetMinimums(items).join('، ')}، وتتكرر بنفس الزيادة بعد ${items}.`;

            return isStorable;
        }

        function updateCreateProductUnitGuidance() {
            const mode = createProductUnitMode();
            const priceHint = document.getElementById('create_price_unit_hint');
            const costHint = document.getElementById('create_cost_unit_hint');
            const quantityHint = document.getElementById('create_quantity_unit_hint');
            const rollQuantityHint = document.getElementById('create_roll_quantity_hint');
            const minInput = document.getElementById('min_stock');
            const minHint = document.getElementById('create_min_stock_unit_hint');

            if (mode === 'fractional') {
                if (priceHint) priceHint.textContent = 'سعر بيع الرول الكامل؛ أسعار المتر أو القص تحددها خيارات التجزئة.';
                if (costHint) costHint.textContent = 'تكلفة الرول الكامل، وليست تكلفة المتر.';
                if (rollQuantityHint) {
                    const rolls = Number(document.getElementById('num_rolls')?.value || 0);
                    const length = Number(document.getElementById('roll_length')?.value || 0);
                    rollQuantityHint.textContent = `${rolls || 0} رول = ${createFormatDecimal(rolls * length)} متر في المخزون.`;
                }
                if (minHint) minHint.textContent = 'الحد الأدنى هنا بعدد الرولات.';
                if (minInput) {
                    minInput.step = '0.01';
                    minInput.setCustomValidity('');
                }
                return;
            }

            if (priceHint) priceHint.textContent = mode === 'set' ? 'سعر بيع الطقم الكامل.' : 'سعر بيع الحبة الواحدة.';
            if (costHint) costHint.textContent = mode === 'set' ? 'تكلفة الطقم الكامل.' : 'تكلفة الحبة الواحدة.';
            if (quantityHint) quantityHint.textContent = mode === 'set' ? 'أدخل الكمية الابتدائية بعدد الأطقم.' : 'أدخل الكمية الابتدائية بعدد الحبات.';
            if (minInput) minInput.step = '1';

            if (mode === 'set') {
                validateCreateSetMinimum();
            } else {
                minInput?.setCustomValidity('');
                minHint?.classList.remove('ui-status-danger');
                minHint?.classList.add('ui-status-warning');
                if (minHint) minHint.textContent = 'الحد الأدنى يحسب بالحبة ويجب أن يكون رقمًا صحيحًا.';
            }
        }

        function prepareCreateSetMinimumForSubmit() {
            if (createProductUnitMode() !== 'set') return;
            const minInput = document.getElementById('min_stock');
            const storageUnit = document.getElementById('min_stock_storage_unit');
            if (!minInput || minInput.value === '') return;
            minInput.value = createFormatDecimal(Math.round(Number(minInput.value || 0)) / createItemsPerUnit());
            if (storageUnit) storageUnit.value = 'set';
        }

        function addFractionRow() {
            // في منتجات الرول، حقل deduction_value يعني عدد الأمتار المستهلكة للخيار.
            // مثال: زجاج أمامي = 1.5 يعني خصم 1.5 متر من مخزون الرول وليس 1.5 رول.
            const container = document.getElementById('fractions_container');
            const row = document.createElement('div');
            row.id = `row_${fractionIndex}`;
            row.className = "ui-product-fraction-row ui-product-fraction-row-card";
            row.innerHTML = `
                <div class="ui-product-fraction-main">
                    <input type="text" name="fractions[${fractionIndex}][option_label]" placeholder="اسم الخيار" class="w-full ui-surface-muted-bg border ui-border ui-title text-sm rounded px-3 py-2">
                </div>
                <div class="ui-product-fraction-compact-field">
                    <input type="number" step="0.01" name="fractions[${fractionIndex}][deduction_value]" placeholder="الاستهلاك بالمتر" class="w-full ui-surface-muted-bg border ui-border ui-title text-sm rounded px-3 py-2">
                </div>
                <div class="ui-product-fraction-compact-field">
                    <input type="number" step="0.01" name="fractions[${fractionIndex}][price]" placeholder="السعر" class="w-full ui-surface-muted-bg border ui-border ui-title text-sm rounded px-3 py-2">
                </div>
                <button type="button" data-product-remove-fraction class="ui-product-fraction-remove ui-status-danger px-2"><i class="fa-solid fa-trash"></i></button>
            `;
            container.appendChild(row);
            fractionIndex++;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('product_type')?.addEventListener('change', () => {
                toggleFractionSection();
                updateCreateProductUnitGuidance();
            });
            document.getElementById('is_splittable')?.addEventListener('change', () => {
                updateCreateProductUnitGuidance();
            });
            document.querySelector('[data-product-add-fraction]')?.addEventListener('click', addFractionRow);
            ['min_stock', 'items_per_unit', 'quantity', 'num_rolls', 'roll_length'].forEach((id) => {
                document.getElementById(id)?.addEventListener('input', updateCreateProductUnitGuidance);
                document.getElementById(id)?.addEventListener('change', updateCreateProductUnitGuidance);
            });
            document.getElementById('productForm')?.addEventListener('submit', function(event) {
                if (!validateCreateSetMinimum()) {
                    event.preventDefault();
                    document.getElementById('min_stock')?.reportValidity();
                    return;
                }
                prepareCreateSetMinimumForSubmit();
            });
            toggleFractionSection();
            updateCreateProductUnitGuidance();
        });
}
