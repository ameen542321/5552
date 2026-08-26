// ينقل سلوك نموذج إنشاء الفاتورة كما هو؛ لا يغير أسماء الحقول أو معادلة الإجمالي والضريبة.
const root = document.querySelector('[data-invoice-create-config]');

if (root) {
    const config = JSON.parse(root.dataset.invoiceCreateConfig || '{}');
    document.addEventListener('DOMContentLoaded', function() {
        const plateInput = document.getElementById('plate_number');

        plateInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\s+/g, ''); // إزالة المسافات الحالية للمعالجة

            // فصل الحروف العربية عن الأرقام
            let letters = value.replace(/[0-9]/g, '').split('').slice(0, 3);
            let numbers = value.replace(/[^\d]/g, '').split('').slice(0, 4);

            // تنسيق الحروف بمسافات (أ ب ج)
            let formattedLetters = letters.join(' ');

            // تنسيق الأرقام
            let formattedNumbers = numbers.join('');

            // الدمج النهائي: حروف + فراغ كبير + أرقام
            let finalValue = formattedLetters;
            if (numbers.length > 0) {
                finalValue += (letters.length > 0 ? '  ' : '') + formattedNumbers;
            }

            e.target.value = finalValue;
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
            const serviceLinesList = document.getElementById('service-lines-list');
            const addServiceLineBtn = document.getElementById('add-service-line');

            function createServiceLineRow(value = '') {
                const row = document.createElement('div');
                row.className = 'flex items-center gap-2 service-line-row';
                row.innerHTML = `
                    <div class="flex-1 min-w-[140px]">
                        <input type="text" name="service_lines[]" value="${value}"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title text-sm"
                               placeholder="مثال: تضليل أمامي / تغيير زيت / تنظيف داخلي">
                    </div>
                    <div class="w-20">
                        <input type="number" step="1" min="0" name="service_qtys[]" value="1"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-qty-input"
                               placeholder="الكمية">
                    </div>
                    <div class="w-24">
                        <input type="number" step="0.01" min="0" name="service_values[]"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-value-input"
                               placeholder="السعر">
                    </div>
                    <div class="w-24">
                        <input type="number" step="0.01" min="0" name="service_totals[]" value="0"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-status-success text-sm text-center service-total-input"
                               placeholder="الإجمالي" readonly>
                    </div>
                    <div class="w-10">
                        <button type="button"
                                class="remove-service-line ui-btn ui-btn-danger w-full h-full ui-text-caption" aria-label="حذف السطر">
                            ✕
                        </button>
                    </div>
                `;
                return row;
            }

            function recalcServiceTotals() {
                let subtotal = 0;
                const rows = serviceLinesList?.querySelectorAll('.service-line-row') || [];
                rows.forEach((row) => {
                    const qtyInput = row.querySelector('input[name="service_qtys[]"]');
                    const valueInput = row.querySelector('input[name="service_values[]"]');
                    const totalInput = row.querySelector('input[name="service_totals[]"]');

                    const qty = parseFloat(qtyInput?.value || '0') || 0;
                    const value = parseFloat(valueInput?.value || '0') || 0;
                    const rowTotal = qty * value;

                    if (totalInput) {
                        totalInput.value = rowTotal.toFixed(2);
                    }
                    subtotal += rowTotal;
                });

                const subtotalInput = document.getElementById('subtotal');
                if (subtotalInput) {
                    subtotalInput.value = subtotal.toFixed(2);
                    subtotalInput.dispatchEvent(new Event('input'));
                }
            }

            addServiceLineBtn?.addEventListener('click', function() {
                serviceLinesList?.appendChild(createServiceLineRow());
                recalcServiceTotals();
            });

            serviceLinesList?.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-service-line');
                if (!removeBtn) return;
                const rows = serviceLinesList.querySelectorAll('.service-line-row');
                if (rows.length <= 1) {
                    const input = rows[0]?.querySelector('input[name="service_lines[]"]');
                    if (input) input.value = '';
                    const valueInput = rows[0]?.querySelector('input[name="service_values[]"]');
                    if (valueInput) valueInput.value = '';
                    const totalInput = rows[0]?.querySelector('input[name="service_totals[]"]');
                    if (totalInput) totalInput.value = '0.00';
                    const qtyInput = rows[0]?.querySelector('input[name="service_qtys[]"]');
                    if (qtyInput) qtyInput.value = '1';
                    recalcServiceTotals();
                    return;
                }
                removeBtn.closest('.service-line-row')?.remove();
                recalcServiceTotals();
            });

            serviceLinesList?.addEventListener('input', function(e) {
                if (e.target.closest('.service-line-row')) {
                    recalcServiceTotals();
                }
            });

            recalcServiceTotals();
        });

    document.addEventListener('DOMContentLoaded', function() {
            const subtotalInput = document.getElementById('subtotal');
            const taxRateInput = document.getElementById('tax_rate');
            const taxDisplay = document.getElementById('tax_amount_display');
            const totalDisplay = document.getElementById('total_amount_display');
            const taxWarning = document.getElementById('tax_warning');

            // جلب الرقم الضريبي للمتجر بشكل آمن حسب السياق (مالك/محاسب)
            const storeTaxNumber = config.storeTaxNumber || '';
            const hasTaxNumber = storeTaxNumber.trim() !== "";

            function calculate() {
                const subtotal = parseFloat(subtotalInput.value) || 0;
                const taxRate = parseFloat(taxRateInput.value) || 0;

                const taxAmount = subtotal * (taxRate / 100);
                const total = subtotal + taxAmount;

                taxDisplay.innerText = taxAmount.toFixed(2) + ' ر.س';
                totalDisplay.innerText = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                // منطق التنبيه
                if(taxRate > 0 && !hasTaxNumber) {
                    taxWarning.classList.remove('hidden');
                } else {
                    taxWarning.classList.add('hidden');
                }
            }

            subtotalInput.addEventListener('input', calculate);
            taxRateInput.addEventListener('change', calculate);
            calculate(); // تشغيل أولي
        });
}
