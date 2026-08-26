// ينقل تفاعل أسطر خدمة تعديل الفاتورة كما هو دون تغيير الحقول أو قيم النموذج.
if (document.querySelector('[data-invoice-edit-interface]')) {
    document.addEventListener('DOMContentLoaded', function () {
            const serviceLinesList = document.getElementById('service-lines-list');
            const addServiceLineBtn = document.getElementById('add-service-line');
            const subtotalInput = document.querySelector('input[name="subtotal"]');
            const taxRateInput = document.querySelector('input[name="tax_rate"]');
            if (!serviceLinesList || !addServiceLineBtn) {
                return;
            }
            const hasProductItems = document.querySelectorAll('input[name="item_ids[]"]').length > 0;

            function recalculateLineTotal(row) {
                const qtyInput = row.querySelector('.service-qty-input');
                const valueInput = row.querySelector('.service-value-input');
                const totalInput = row.querySelector('.service-total-input');

                const qty = parseFloat(qtyInput?.value || 0);
                const value = parseFloat(valueInput?.value || 0);
                totalInput.value = ((isNaN(qty) ? 0 : qty) * (isNaN(value) ? 0 : value)).toFixed(2);
                recalculateInvoiceTotalsFromServices();
            }

            function recalculateInvoiceTotalsFromServices() {
                if (hasProductItems || !subtotalInput) {
                    return;
                }

                let subtotal = 0;
                serviceLinesList.querySelectorAll('.service-total-input').forEach((input) => {
                    const value = parseFloat(input.value || 0);
                    subtotal += isNaN(value) ? 0 : value;
                });

                subtotalInput.value = subtotal.toFixed(2);

                if (taxRateInput) {
                    const currentRate = parseFloat(taxRateInput.value || 0);
                    taxRateInput.value = isNaN(currentRate) ? '0' : currentRate;
                }
            }

            function attachRowListeners(row) {
                row.querySelector('.service-qty-input')?.addEventListener('input', () => recalculateLineTotal(row));
                row.querySelector('.service-value-input')?.addEventListener('input', () => recalculateLineTotal(row));
                row.querySelector('.remove-service-line')?.addEventListener('click', () => {
                    const rows = serviceLinesList.querySelectorAll('.service-line-row');
                    if (rows.length <= 1) {
                        row.querySelector('input[name="service_lines[]"]').value = '';
                        row.querySelector('.service-qty-input').value = 1;
                        row.querySelector('.service-value-input').value = '';
                        recalculateLineTotal(row);
                        return;
                    }
                    row.remove();
                    recalculateInvoiceTotalsFromServices();
                });
                recalculateLineTotal(row);
            }

            function createServiceLineRow() {
                const row = document.createElement('div');
                row.className = 'grid grid-cols-12 gap-2 items-center service-line-row';
                row.innerHTML = `
                    <div class="col-span-12 md:col-span-5">
                        <input type="text" name="service_lines[]"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title text-sm"
                               placeholder="اسم المنتج / الخدمة">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <input type="number" step="1" min="0" name="service_qtys[]" value="1"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-qty-input">
                    </div>
                    <div class="col-span-4 md:col-span-2">
                        <input type="number" step="0.01" min="0" name="service_values[]"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-value-input">
                    </div>
                    <div class="col-span-3 md:col-span-2">
                        <input type="number" step="0.01" min="0" name="service_totals[]" value="0"
                               class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-status-success text-sm text-center service-total-input" readonly>
                    </div>
                    <div class="col-span-1 md:col-span-1">
                        <button type="button" class="remove-service-line ui-btn ui-btn-danger w-full h-[38px] ui-text-caption" aria-label="حذف السطر">✕</button>
                    </div>
                `;
                attachRowListeners(row);
                return row;
            }

            serviceLinesList.querySelectorAll('.service-line-row').forEach(attachRowListeners);
            addServiceLineBtn.addEventListener('click', () => serviceLinesList.appendChild(createServiceLineRow()));
        });
}
