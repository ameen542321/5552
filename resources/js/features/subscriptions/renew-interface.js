// استخراج واجهة فقط؛ تبقى النماذج والمسارات وشروط العمليات كما هي.
if (document.querySelector('[data-subscription-renew-interface]')) {
    // تعريف أسعار الخطط مع الضريبة
        const plans = {
            basic: { price: 500, tax: 75, total: 575 },
            silver: { price: 1400, tax: 210, total: 1610 },
            gold: { price: 2700, tax: 405, total: 3105 }
        };

        // تحديث الأسعار عند اختيار خطة
        function updatePrices(planKey) {
            const plan = plans[planKey];
            if (plan) {
                document.getElementById('planPrice').innerText = plan.price.toLocaleString() + ' ريال';
                document.getElementById('taxAmount').innerText = plan.tax.toLocaleString() + ' ريال';
                document.getElementById('totalPrice').innerHTML = plan.total.toLocaleString() + ' <span class="text-sm ui-text-muted">ريال</span>';
            }
        }

        // تفعيل البطاقات عند النقر
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', function() {
                // إزالة التحديد من جميع البطاقات
                document.querySelectorAll('.plan-card').forEach(c => {
                    c.classList.remove('ui-border', 'shadow-2xl', 'scale-105', 'z-10');
                    c.classList.add('ui-border');
                });

                // إضافة التحديد للبطاقة الحالية
                this.classList.remove('ui-border');
                this.classList.add('ui-border', 'shadow-2xl');

                // إذا كانت البطاقة الفضية، أضف scale
                if (this.dataset.plan === 'silver') {
                    this.classList.add('scale-105', 'z-10');
                }

                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;

                // تحديث الأسعار
                updatePrices(radio.value);
            });
        });

        // تحديد الخطة الفضية افتراضياً عند تحميل الصفحة
        window.addEventListener('load', function() {
            updatePrices('silver');
        });

        // تفعيل زر التأكيد عند الموافقة على الشروط
        document.querySelector('.form-checkbox').addEventListener('change', function() {
            const submitBtn = document.getElementById('submitBtn');
            if (this.checked) {
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.disabled = false;
            } else {
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.disabled = true;
            }
        });

        // تعطيل زر التأكيد في البداية حتى يتم الموافقة على الشروط
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').classList.add('opacity-50', 'cursor-not-allowed');
}
