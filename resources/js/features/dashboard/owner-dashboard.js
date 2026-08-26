// سلوك عرض لوحة المالك مستخرج كما هو؛ الأرقام والملخصات محسوبة في الخادم وتصل عبر عقد إعداد آمن.
const root = document.querySelector('[data-owner-dashboard-config]');

if (root) {
    const config = JSON.parse(root.dataset.ownerDashboardConfig || '{}');
    (function () {
        const labels   = config.chartLabels || [];
        const sales    = config.chartSales || [];
        const expenses = config.chartExpenses || [];
        const credit   = config.chartCredit || [];

        const canvas = document.getElementById('smartChart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        function drawChart() {
            const dpr = window.devicePixelRatio || 1;
            const cssWidth = canvas.clientWidth || 600;
            const cssHeight = canvas.clientHeight || 260;

            canvas.width = Math.floor(cssWidth * dpr);
            canvas.height = Math.floor(cssHeight * dpr);

            // [تعديل آمن] منع تراكم الـ scale عند كل resize لضمان دقة الرسم.
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            const margin = { top: 20, right: 16, bottom: 36, left: 46 };
            const innerWidth  = cssWidth  - margin.left - margin.right;
            const innerHeight = cssHeight - margin.top  - margin.bottom;

            if (innerWidth <= 0 || innerHeight <= 0) return;

            const maxValue = Math.max(
                10,
                ...sales,
                ...expenses,
                ...credit
            );

            const stepX = innerWidth / Math.max(labels.length - 1, 1);

            function yScale(value) {
                return margin.top + innerHeight - (value / maxValue) * innerHeight;
            }

            // شبكة خلفية (محور Y)
            ctx.strokeStyle = 'rgba(148, 163, 184, 0.18)';
            ctx.lineWidth = 1;
            const ticks = 4;
            for (let i = 0; i <= ticks; i++) {
                const y = margin.top + (innerHeight / ticks) * i;
                ctx.beginPath();
                ctx.moveTo(margin.left, y);
                ctx.lineTo(margin.left + innerWidth, y);
                ctx.stroke();

                const val = Math.round(maxValue - (maxValue / ticks) * i);
                ctx.fillStyle = 'rgba(148, 163, 184, 0.75)';
                ctx.font = '11px sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(val.toLocaleString('en-US'), margin.left - 6, y + 3);
            }

            // محور X (عرض تواريخ متباعدة لتجنب التزاحم)
            ctx.fillStyle = 'rgba(148, 163, 184, 0.75)';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            const labelStep = Math.max(1, Math.ceil(labels.length / 6));
            labels.forEach((label, i) => {
                if (i % labelStep !== 0 && i !== labels.length - 1) return;
                const x = margin.left + i * stepX;
                ctx.fillText(label.slice(5), x, margin.top + innerHeight + 16);
            });

            function drawLine(data, color) {
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.beginPath();

                data.forEach((v, i) => {
                    const x = margin.left + i * stepX;
                    const y = yScale(v);
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                });

                ctx.stroke();

                // نقاط البيانات
                ctx.fillStyle = color;
                data.forEach((v, i) => {
                    const x = margin.left + i * stepX;
                    const y = yScale(v);
                    ctx.beginPath();
                    ctx.arc(x, y, 2.5, 0, Math.PI * 2);
                    ctx.fill();
                });
            }

            const chartTheme = getComputedStyle(document.documentElement);
            drawLine(sales, chartTheme.getPropertyValue('--ui-success-text').trim());    // مبيعات
            drawLine(expenses, chartTheme.getPropertyValue('--ui-danger-text').trim()); // مصروفات
            drawLine(credit, chartTheme.getPropertyValue('--ui-info-text').trim());   // مديونيات
        }

        drawChart();
        window.addEventListener('resize', drawChart);
    })();

    document.addEventListener('DOMContentLoaded', function () {
        // تفاصيل كل بطاقة موزعة حسب المتجر، مرسلة من الكنترولر كـ JSON.
        const storeBreakdowns = config.storeBreakdowns || [];
        window.ownerDashboardStoreBreakdowns = storeBreakdowns;
        // تعريف العناوين والقيم والنص التوضيحي لكل بطاقة قابلة للنقر.
        const metricDefinitions = config.metricDefinitions || {};
        window.ownerDashboardMetricDefinitions = metricDefinitions;

        const modal = document.getElementById('metric-modal');
        const closeBtn = document.getElementById('metric-modal-close');
        const titleEl = document.getElementById('metric-modal-title');
        const valueEl = document.getElementById('metric-modal-value');
        const detailsEl = document.getElementById('metric-modal-details');
        const salaryHelp = document.getElementById('metric-modal-salary-help');

        // تعقيم النصوص القادمة من قاعدة البيانات قبل إدراجها داخل innerHTML.
        // يمنع تفسير اسم المتجر كوسوم HTML أو JavaScript غير موثوق.
        function escapeMetricHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function formatByMetric(metricKey, amount) {
            const numeric = Number(amount || 0);
            if (metricKey === 'expenses_today' || metricKey === 'expenses_month') {
                return `<span class="ui-status-danger font-bold">${numeric.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>`;
            }
            if (metricKey === 'profit_today' || metricKey === 'profit_month') {
                const color = numeric >= 0 ? 'ui-status-success' : 'ui-status-danger';
                return `<span class="${color} font-bold">${numeric.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>`;
            }
            return `<span class="ui-status-info font-bold">${numeric.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>`;
        }

        document.querySelectorAll('.metric-card').forEach((card) => {
            card.addEventListener('click', () => {
                const key = card.dataset.metric;
                const data = metricDefinitions[key];
                if (!data) return;
                titleEl.textContent = data.title;
                valueEl.textContent = data.value;
                salaryHelp?.classList.toggle('hidden', key !== 'salaries_month');
                const rows = storeBreakdowns.map((store) => {
                    return `<li class="flex items-center justify-between ui-border-bottom py-2">
                        <span class="ui-text-soft">${escapeMetricHtml(store.store_name)}</span>
                        <span>${formatByMetric(key, store[key])} <span class="ui-text-muted ui-text-caption">ر.س</span></span>
                    </li>`;
                }).join('');

                detailsEl.innerHTML = `<ul class="max-h-52 overflow-y-auto pr-1">${rows || '<li class="ui-text-muted py-2">لا توجد متاجر متاحة.</li>'}</ul>`;
                modal.classList.remove('hidden');
            });
        });

        closeBtn?.addEventListener('click', () => modal.classList.add('hidden'));
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.add('hidden');
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        // بيانات الموظفين الجاهزة لحساب وعرض الراتب والسحب والمتبقي لكل متجر.
        const salaryRows = config.salaryRows || [];
        const modal = document.getElementById('salary-withdrawals-modal');
        const openButton = document.getElementById('salary-after-withdrawals-card');
        const closeButton = document.getElementById('salary-withdrawals-close');
        const storesContainer = document.getElementById('salary-withdrawals-stores');

        // حماية النصوص القادمة من قاعدة البيانات قبل إدراجها داخل HTML ديناميكي.
        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        // توحيد عرض جميع مبالغ نافذة الرواتب إلى منزلتين عشريتين.
        function formatSalary(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        // تجميع الموظفين حسب المتجر وبناء جدول قابل للفتح لكل متجر.
        function renderSalaryStores() {
            if (!storesContainer) return;

            const groupedStores = salaryRows.reduce((stores, employee) => {
                const storeName = employee.store_name || 'متجر غير معروف';
                if (!stores[storeName]) stores[storeName] = [];
                stores[storeName].push(employee);
                return stores;
            }, {});

            const storeEntries = Object.entries(groupedStores);
            if (!storeEntries.length) {
                storesContainer.innerHTML = '<p class="text-sm ui-text-muted text-center py-6">لا توجد بيانات رواتب متاحة.</p>';
                return;
            }

            storesContainer.innerHTML = storeEntries.map(([storeName, employees], storeIndex) => {
                const salaryTotal = employees.reduce((total, employee) => total + Number(employee.salary || 0), 0);
                const withdrawalsTotal = employees.reduce((total, employee) => total + Number(employee.withdrawals_total || 0), 0);
                const remainingTotal = employees.reduce((total, employee) => total + Number(employee.salary_remaining || 0), 0);
                const absenceTotal = employees.reduce((total, employee) => total + Number(employee.absence_deduction || 0), 0);
                const rows = employees.map((employee) => `
                    <tr class="ui-border-bottom last:border-0">
                        <td data-label="الموظف" class="py-3 px-2 ui-text-soft">${escapeHtml(employee.name)}</td>
                        <td data-label="الراتب" class="py-3 px-2 ui-status-info">${formatSalary(employee.salary)}${Number(employee.suspended_days || 0) > 0 ? `<div class="ui-text-caption ui-status-warning">إيقاف ${employee.suspended_days} يوم</div>` : ''}</td>
                        <td data-label="إجمالي السحب" class="py-3 px-2 ui-status-danger">${formatSalary(employee.withdrawals_total)}</td>
                        <td data-label="خصم الغياب" class="py-3 px-2 ui-status-warning">${formatSalary(employee.absence_deduction)}${Number(employee.absence_days || 0) > 0 ? `<div class="ui-text-caption ui-status-warning">غياب ${employee.absence_days} يوم</div>` : ''}</td>
                        <td data-label="المتبقي" class="py-3 px-2 ui-status-success font-bold">${formatSalary(employee.salary_remaining)}</td>
                    </tr>
                `).join('');

                return `
                    <div class="rounded-xl ui-border overflow-hidden">
                        <button type="button"
                                class="salary-store-toggle w-full p-4 flex items-center justify-between gap-3 text-right ui-hover-surface transition"
                                data-target="salary-store-${storeIndex}">
                            <div>
                                <p class="text-sm font-bold ui-title">${escapeHtml(storeName)}</p>
                                <p class="ui-text-caption ui-text-muted mt-1">${employees.length} موظف — المتبقي ${formatSalary(remainingTotal)}</p>
                            </div>
                            <div class="flex items-center gap-3 ui-text-caption">
                                <span class="ui-status-info">الرواتب ${formatSalary(salaryTotal)}</span>
                                <span class="ui-status-danger">السحب ${formatSalary(withdrawalsTotal)}</span>
                                <span class="ui-status-warning">الغياب ${formatSalary(absenceTotal)}</span>
                                <i class="fa-solid fa-chevron-down ui-text-muted"></i>
                            </div>
                        </button>
                        <div id="salary-store-${storeIndex}" class="hidden border-t ui-border">
                            <table class="ui-responsive-table w-full ui-text-caption text-right">
                                <thead class="ui-input-bg ui-text-muted">
                                    <tr>
                                        <th class="py-2 px-2">الموظف</th>
                                        <th class="py-2 px-2">الراتب</th>
                                        <th class="py-2 px-2">إجمالي السحب</th>
                                        <th class="py-2 px-2">خصم الغياب</th>
                                        <th class="py-2 px-2">المتبقي</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                    </div>
                `;
            }).join('');
        }

        openButton?.addEventListener('click', function () {
            renderSalaryStores();
            modal?.classList.remove('hidden');
        });
        closeButton?.addEventListener('click', () => modal?.classList.add('hidden'));
        modal?.addEventListener('click', function (event) {
            if (event.target === modal) modal.classList.add('hidden');
        });
        storesContainer?.addEventListener('click', function (event) {
            const toggle = event.target.closest('.salary-store-toggle');
            if (!toggle) return;
            const details = document.getElementById(toggle.dataset.target);
            details?.classList.toggle('hidden');
            toggle.querySelector('.fa-chevron-down')?.classList.toggle('rotate-180');
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        // مسار JSON الذي يحدّث بطاقات اليوم وآخر عملية دون إعادة تحميل الصفحة.
        const snapshotUrl = config.snapshotUrl;
        const dashboardDate = config.dashboardDate;
        const statusDot = document.getElementById('live-status-dot');
        // آخر معرف عُرض؛ يستخدم لتفعيل وميض البطاقة عند وصول عملية جديدة فقط.
        let latestOperationId = null;
        let consecutiveSnapshotFailures = 0;
        let snapshotTimer = null;
        let activeSnapshotController = null;
        let snapshotRequestSequence = 0;

        // تنسيق الأرقام الحية مع منزلتين كحد أقصى.
        function formatNumber(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            });
        }

        // تحديث قيمة بطاقة واحدة بواسطة معرف عنصر القيمة داخل المكوّن.
        function updateCardValue(valueId, value) {
            const valueElement = document.getElementById(valueId);
            if (valueElement) valueElement.textContent = formatNumber(value);
        }

        function updateConnectionStatus(isConnected, updatedAt = null) {
            const updatedElement = document.getElementById('live-updated-at');

            statusDot?.classList.toggle('ui-dot-success', isConnected);
            statusDot?.classList.toggle('ui-dot-warning', !isConnected);
            statusDot?.classList.toggle('animate-pulse', isConnected);

            if (!updatedElement) return;
            if (isConnected && updatedAt) {
                updatedElement.textContent = `آخر تحديث: ${updatedAt}`;
            } else if (!isConnected) {
                if (!updatedElement.textContent.startsWith('تعذر التحديث')) {
                    updatedElement.textContent = `تعذر التحديث — ${updatedElement.textContent}`;
                }
            }
        }

        // جلب اللقطة اليومية وتحديث البطاقات والعداد وآخر عملية كل ثلاث ثوانٍ.
        async function refreshDailySnapshot() {
            if (document.hidden) return;

            const requestSequence = ++snapshotRequestSequence;
            activeSnapshotController?.abort();
            activeSnapshotController = new AbortController();

            try {
                const url = new URL(snapshotUrl, window.location.origin);
                url.searchParams.set('date', dashboardDate);
                url.searchParams.set('_', Date.now().toString());

                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: activeSnapshotController.signal,
                });
                if (!response.ok) {
                    throw new Error(`Snapshot request failed: ${response.status}`);
                }

                const data = await response.json();
                if (requestSequence !== snapshotRequestSequence) {
                    return;
                }

                consecutiveSnapshotFailures = 0;
                updateCardValue('daily-profit-value', data.profit_today);
                updateCardValue('daily-sales-value', data.sales_today);
                updateCardValue('daily-expenses-value', data.expenses_today);
                updateCardValue('daily-products-cost-value', data.products_cost_today);

                const metricDefinitions = window.ownerDashboardMetricDefinitions;
                if (metricDefinitions) {
                    metricDefinitions.profit_today.value = `${formatNumber(data.profit_today)} ر.س`;
                    metricDefinitions.sales_today.value = `${formatNumber(data.sales_today)} ر.س`;
                    metricDefinitions.expenses_today.value = `${formatNumber(data.expenses_today)} ر.س`;
                    metricDefinitions.products_cost_today.value = `${formatNumber(data.products_cost_today)} ر.س`;
                }

                const countElement = document.getElementById('live-operations-count');
                if (countElement) countElement.textContent = formatNumber(data.operations_count);

                const latestCard = document.getElementById('live-operation-card');
                const productElement = document.getElementById('live-operation-product');
                const storeElement = document.getElementById('live-operation-store');
                const amountElement = document.getElementById('live-operation-amount');
                const timeElement = document.getElementById('live-operation-time');
                if (data.latest_operation) {
                    if (productElement) {
                        productElement.textContent = data.latest_operation.description;
                        productElement.classList.toggle('ui-status-info', Boolean(data.latest_operation.is_tint));
                    }
                    if (storeElement) storeElement.textContent = data.latest_operation.store_name;
                    if (amountElement) amountElement.textContent = formatNumber(data.latest_operation.amount);
                    if (timeElement) timeElement.textContent = data.latest_operation.time || '--:--';
                } else {
                    if (productElement) productElement.textContent = 'لا توجد عمليات بيع في التاريخ المحدد.';
                    if (storeElement) storeElement.textContent = '—';
                    if (amountElement) amountElement.textContent = '0.00';
                    if (timeElement) timeElement.textContent = '--:--';
                }
                if (latestCard && data.latest_operation?.id && latestOperationId !== data.latest_operation.id) {
                    latestCard.classList.add('ui-status-info-border', 'ui-status-info-bg');
                    window.setTimeout(() => {
                        latestCard.classList.remove('ui-status-info-border', 'ui-status-info-bg');
                    }, 1800);
                    latestOperationId = data.latest_operation.id;
                }

                updateConnectionStatus(true, data.updated_at);
            } catch (error) {
                if (error.name === 'AbortError') return;
                consecutiveSnapshotFailures++;
                if (consecutiveSnapshotFailures >= 2) {
                    updateConnectionStatus(false);
                }
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshDailySnapshot();
            }
        });

        refreshDailySnapshot();
        snapshotTimer = window.setInterval(refreshDailySnapshot, 5000);
        window.addEventListener('beforeunload', () => window.clearInterval(snapshotTimer));
    });
}
