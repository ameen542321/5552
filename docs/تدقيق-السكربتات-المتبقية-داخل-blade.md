# تدقيق السكربتات المتبقية داخل Blade

> تاريخ التدقيق: 2026-07-29. هذا الحصر يطبق المرحلة السابعة من خارطة إكمال الواجهات، ولا ينقل منطقًا ماليًا أو مخزنيًا ولا يقرأ أو يكتب بيانات قاعدة البيانات.

## النتيجة المختصرة

- انخفض العدد من 24 إلى واجهتي Blade تحتويان وسم `<script>` واحدًا أو أكثر بعد استخراج واجهة البيع السريع.
- ليست جميع الوسوم JavaScript تنفيذيًا: ملف فهرس المنتجات يحتوي عقد JSON من نوع `application/json` تقرؤه وحدة مستخرجة بالفعل.
- جرى تصنيف الملفات إلى أربع مجموعات حتى لا يختلط استخراج العرض العام مع منطق البيع والمخزون والفواتير والموظفين.
- اكتملت ملفات المجموعة الأولى القابلة للنقل، وبقي محملا الثيم وSweetAlert في قالب اللوحة لسبب تقني موثق؛ أما المجموعتان الثالثة والرابعة فتحتاجان تثبيت عقود واختبارات تدفق قبل النقل.

## 1. عرض عام يمكن نقله مباشرة

| الملف | حجم السكربت التقريبي | سبب التصنيف | الإجراء التالي |
| --- | ---: | --- | --- |
| `resources/views/accountants/pos/searchProduct.blade.php` | 12 سطرًا | إغلاق تفاصيل بزر Escape وتركيز حقل البحث فقط | مكتمل: `general-view-actions.js` |
| `resources/views/cashier/internal-use/report.blade.php` | 17 سطرًا | تنسيق فتح عنصر تفاصيل واحد دون تعديل بيانات | مكتمل: `general-view-actions.js` |
| `resources/views/notifications/index.blade.php` | 12 سطرًا | تحديد جميع مربعات الاختيار محليًا | مكتمل: `notifications/interface-actions.js` |
| `resources/views/notifications/send.blade.php` | 31 سطرًا | حالة Alpine لاختيار المستلمين دون إرسال مباشر | مكتمل: `notifications/interface-actions.js` |
| `resources/views/modules/purchase-orders/user/index.blade.php` | 14 سطرًا | مؤشر تحميل ومنع نقر مزدوج لنموذج الفلترة | مكتمل: `purchase-orders/index-filter.js` |
| `resources/views/dashboard/app.blade.php` | وسمان تحميل | تحميل الثيم وSweetAlert من مصادر خارجية، دون منطق صفحة | روجع وأبقي: الثيم متزامن لمنع الوميض وSweetAlert محمل مكتبة مشترك |

## 2. عرض يحتاج إعدادًا آمنًا من Blade

| الملف | حجم السكربت التقريبي | البيانات المطلوبة | الإجراء التالي |
| --- | ---: | --- | --- |
| `resources/views/user/stores/show.blade.php` | 59 سطرًا | بيانات ومحاور رسم المبيعات والأرباح | مكتمل: عقد آمن + `stores/store-sales-chart.js` |
| `resources/views/dashboard/user/index.blade.php` | 431 سطرًا | ملخصات ورواتب ومصروفات ومشتريات ومسارات عرض | مكتمل: عقد آمن + `dashboard/owner-dashboard.js` دون تغيير مصادر الأرقام |
| `resources/views/invoices/create.blade.php` | 161 سطرًا | بيانات الفاتورة والحقول الديناميكية | مكتمل: عقد آمن + `invoices/create-form.js` مع إبقاء الحقول والمعادلات كما هي |
| `resources/views/invoices/edit.blade.php` | 92 سطرًا | بيانات الفاتورة الحالية | مكتمل: عقد تفعيل + `invoices/edit-form.js` مع إبقاء الحقول كما هي |

## 3. منطق مالي أو موظفين يحتاج عقودًا واختبارات نطاقية

| الملف | حجم السكربت التقريبي | سبب الحماية |
| --- | ---: | --- |
| `resources/views/accountants/pos/expense.blade.php` | 25 سطرًا | مكتمل: رسائل الواجهة وإغلاق المودال في `accountant/expense-interface.js` دون تغيير العملية |
| `resources/views/components/employee/debt-form.blade.php` | 196 سطرًا | مكتمل: طرق الدفع والتأكيد والإرسال في `employees/debt-interface.js` مع مسارات ومبالغ من عقود Blade |
| `resources/views/employees/actions.blade.php` | 140 سطرًا | مكتمل: تأكيدات الواجهة وفحص البريد في `employees/actions-interface.js` مع بقاء المسارات |
| `resources/views/employees/index.blade.php` | 39 سطرًا | مكتمل: تأكيد الحالة في `employees/index-interface.js` |
| `resources/views/subscriptions/renew.blade.php` | 65 سطرًا | مكتمل: عرض اختيار الخطة في `subscriptions/renew-interface.js` دون تغيير نموذج التجديد |
| `resources/views/user/stores/shift-gaps.blade.php` | 41 سطرًا | مكتمل: رسائل التأكيد في `shifts/gap-confirmations.js` دون تغيير القرار المرسل |

لا تنقل هذه المجموعة قبل تثبيت أسماء الحقول والمسارات وطرق HTTP والنصوص التأكيدية، ثم اختبار payload قبل النقل وبعده.

## 4. منطق بيع أو مخزون أو طلبات شراء يحتاج موافقة واختبارات تدفق

| الملف | حجم السكربت التقريبي | سبب الحماية |
| --- | ---: | --- |
| `resources/views/cashier/internal-use/create.blade.php` | 290 سطرًا | مكتمل: واجهة المنتجات والكميات في `cashier/internal-use-interface.js` مع عقد بيانات المنتجات |
| `resources/views/cashier/quick-sale/index.blade.php` | 610 أسطر | مكتمل: البيع والفاتورة والدفع والمديونية والكميات في `cashier/quick-sale-interface.js` مع عقد مسارات وحالات آمن |
| `resources/views/cashier/quick-sale/partials/tint-modal.blade.php` | 194 سطرًا | مكتمل: منشئ التضليل وخياراته في `cashier/tint-sale-interface.js` مع عقد بيانات المنتجات |
| `resources/views/modules/purchase-orders/user/create.blade.php` | 745 سطرًا | مكتمل: البنود والبحث والمسودة والتأكيد في `purchase-orders/form-interface.js` مع عقد إعداد آمن |
| `resources/views/modules/purchase-orders/user/show.blade.php` | 406 أسطر | مكتمل: الاستلام والاعتماد وتغييرات التكلفة في `purchase-orders/show-interface.js` مع عقد حالة آمن |
| `resources/views/user/stores/products/create.blade.php` | 172 سطرًا | مكتمل: إرشادات الوحدات والحد الأدنى والتجزئة في `store-products/product-create-interface.js` |
| `resources/views/user/stores/products/edit.blade.php` | 202 سطرًا | مكتمل: عرض وتحويل الوحدات والحد الأدنى والتجزئة في `store-products/product-edit-interface.js` |

اكتمل نقل سكربتات هذه المجموعة دون تغيير الحسابات أو حدود الكميات أو المسارات. يبقى اختبار تدفق البيع الكامل في بيئة تجريبية قابلة للرجوع بوابة مستقلة قبل النشر.

## 5. عقد بيانات غير تنفيذي

| الملف | الحالة |
| --- | --- |
| `resources/views/user/stores/products/index.blade.php` | يحتوي `<script type="application/json">` لتغذية وحدة فهرس المنتجات؛ ليس handler أو JavaScript تنفيذيًا، ويبقى استثناء بيانات موثقًا إلى أن يعتمد المشروع نمط عقد موحدًا بديلًا. |

## بوابة القبول للدفعة التالية

1. لم يبق سكربت صفحة تنفيذي داخل Blade؛ الموجود محملا الثيم وSweetAlert وعقد JSON غير تنفيذي موثق فقط.
2. لا تتغير أسماء الحقول أو المسارات أو طرق HTTP أو نصوص التأكيد.
3. يضاف عقد ساكن يمنع عودة السكربت التنفيذي إلى كل واجهة مستخرجة.
4. ينجح `npm run test:visual` ثم `npm run build`.
5. لا يبدأ اختبار موظف أو عملية مالية فعلية قبل تزويد حسابات وبيانات متجر تجريبية صريحة وتحديد السجلات المسموح إنشاؤها وحذفها.
