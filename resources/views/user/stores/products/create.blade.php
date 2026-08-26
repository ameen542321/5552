@extends('dashboard.app')

@section('title', 'إضافة منتج – متجر ' . $store->name)

@section('content')

<div class="ui-product-form-page" data-store-product-form>
    {{-- تعرض أخطاء الحفظ عبر SweetAlert في الأسفل لتبقى الرسائل عربية وواضحة. --}}

    <div class="ui-product-form-header">
        <a href="{{ route('user.stores.products.index', $store->id) }}"
           class="ui-product-form-back inline-flex items-center gap-2 px-4 py-2 rounded-lg ui-surface-muted-bg border ui-border ui-text-muted transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع إلى المنتجات</span>
        </a>
        <h1 class="ui-product-form-title text-2xl font-bold ui-title">إضافة منتج جديد</h1>
    </div>

    <div class="ui-product-form-card ui-surface-muted-bg border ui-border rounded-xl shadow-2xl">
        <form action="{{ route('user.stores.products.store', $store->id) }}" method="POST" enctype="multipart/form-data" id="productForm">
            @csrf

            @php
                $mainCategories = $categories->where('is_main_category', 1);
                $normalCategories = $categories->where('is_main_category', 0);
            @endphp

            {{-- القسم --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">القسم</label>
                <select name="category_id" id="category_id" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2 outline-none  ">
                    @foreach($mainCategories as $category)
                        <option value="{{ $category->id }}" data-category-name="{{ $category->name }}" @selected(old('category_id', $selectedCategory) == $category->id)>{{ $category->name }} (نشاط)</option>
                    @endforeach
                    @foreach($normalCategories as $category)
                        <option value="{{ $category->id }}" data-category-name="{{ $category->name }}" @selected(old('category_id', $selectedCategory) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- نوع المنتج --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">نوع المنتج</label>
                <select name="product_type" id="product_type"
                        class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2 font-bold ui-status-info outline-none  ">
                    <option value="standard" @selected(old('product_type') == 'standard')>📦 منتج عادي (بالوحدة)</option>
                    <option value="fractional" @selected(old('product_type') == 'fractional')>✂️ منتج قابل للتجزئة (رول/قص)</option>
                </select>
            </div>

            {{-- خيار استخدام المنتج: افتراضيًا للبيع، ويمكن جعله مشتريات للمالك لإخفائه من البيع والجرد والحسابات. --}}
            <div class="mb-6 p-4 ui-status-warning-bg border ui-border rounded-xl">
                <label class="block ui-status-warning mb-2 font-bold">استخدام المنتج</label>
                <select name="usage_type" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <option value="sale" @selected(old('usage_type', 'sale') === 'sale')>للبيع (افتراضي)</option>
                    <option value="owner_purchase" @selected(old('usage_type') === 'owner_purchase')>مشتريات للمالك فقط</option>
                </select>
                <button type="button" data-ui-help-title="شرح استخدام المنتج" data-ui-help-body="منتج مشتريات المالك لا يظهر في البيع أو بحث البيع أو بطاقات المنتجات أو المنخفضة أو الجرد أو حسابات التكلفة، ويظهر فقط في إدارة المنتجات عند تفعيل زر إظهاره وفي صفحات التوريد." title="شرح" aria-label="شرح استخدام المنتج" class="mt-2 inline-flex h-8 w-8 items-center justify-center rounded-full border ui-border ui-status-warning-bg text-sm font-black"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
            </div>

            {{-- إرشادات منتج الرول؛ تتغير حسب القسم المختار وتظهر فقط للرول/القص. --}}
            <div id="fractional_product_guidance" class="mb-6 p-5 ui-status-info-bg border ui-border rounded-xl">
                <div class="flex items-start gap-3 mb-4">
                    <div class="shrink-0 w-10 h-10 rounded-lg ui-status-info-bg ui-status-info flex items-center justify-center">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2"><h3 id="fractional_guidance_title" class="ui-status-info font-bold">دليل إدخال منتج رول/قص</h3><button type="button" data-ui-help-title="تعليمات منتج الرول/القص" data-ui-help-body="سجّل كل رول فعلي كمنتج مستقل. أدخل طول الرول الكامل بالمتر، وتكلفة الرول الكامل، ثم أضف خيارات القص مع الاستهلاك بالمتر وسعر البيع." title="تعليمات" aria-label="تعليمات منتج الرول" class="inline-flex h-7 w-7 items-center justify-center rounded-full border ui-border ui-status-info-bg ui-text-caption font-black"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div>
                        <p class="ui-text-caption ui-text-muted mt-1">سجّل كل رول فعلي كمنتج مستقل؛ فالتكلفة والمخزون والمتبقي تُتابع لكل منتج بالمتر.</p>
                    </div>
                </div>

                <div id="tint_product_guidance" class="hidden mb-4 p-4 ui-status-info-bg border ui-border rounded-lg">
                    <p class="ui-status-info font-bold text-sm mb-2"><i class="fa-solid fa-sun ml-1"></i> منتج تابع لقسم تضليل</p>
                    <ul class="space-y-1 ui-text-caption ui-text-muted list-disc list-inside">
                        <li>اسم المنتج بالترتيب: <strong class="ui-title">النوع + الحجم + الدرجة</strong>.</li>
                        <li>أمثلة صحيحة: <strong class="ui-title">كوري كبير 01</strong>، <strong class="ui-title">أمريكي صغير 02</strong>، <strong class="ui-title">مخلوط صغير 01</strong>.</li>
                        <li>خيارات التجزئة القياسية: <strong class="ui-title">كامل، أمامي، خلفي، دريشة</strong>. لا تضف «مخصص»؛ مكانه موجود في شاشة البيع.</li>
                        <li>استهلاك «دريشة» وسعرها يُدخلان لدريشة واحدة، والنظام يضربهما في العدد عند البيع.</li>
                    </ul>
                </div>

                <div id="upholstery_product_guidance" class="hidden mb-4 p-4 ui-status-warning-bg border ui-border rounded-lg">
                    <p class="ui-status-warning font-bold text-sm mb-2"><i class="fa-solid fa-couch ml-1"></i> منتج تابع لقسم تنجيد وتلابيس</p>
                    <p class="ui-text-caption ui-text-muted">اكتب اسمًا يميز الخامة واللون أو المقاس، مثل: <strong class="ui-title">جلد أسود عرض 1.5 متر</strong>. سمِّ خيارات القص حسب الأعمال الفعلية التي تبيعها، وحدد استهلاك كل خيار بالمتر.</p>
                </div>

                <div id="general_roll_guidance" class="hidden mb-4 p-4 ui-surface-muted-bg border ui-border rounded-lg">
                    <p class="ui-text-muted font-bold text-sm mb-1">منتج رول في قسم آخر</p>
                    <p class="ui-text-caption ui-text-muted">استخدم اسمًا واضحًا يميز المنتج، وأنشئ خيارات قص بأسماء يفهمها العامل مع استهلاك وسعر كل خيار.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="p-3 ui-surface-muted-bg border ui-border rounded-lg">
                        <span class="block ui-status-info font-bold mb-1">طول الرول وسعر التكلفة</span>
                        <p class="ui-text-muted">أدخل طول الرول الكامل بالمتر، وسعر تكلفة <strong class="ui-title">الرول الكامل</strong> وليس سعر المتر.</p>
                    </div>
                    <div class="p-3 ui-surface-muted-bg border ui-border rounded-lg">
                        <span class="block ui-status-info font-bold mb-1">عدد الرولات والمخزون</span>
                        <p class="ui-text-muted">عند الإضافة: المخزون بالمتر = عدد الرولات × طول الرول. وعند التعديل غيّر الكمية من إدارة المخزون.</p>
                    </div>
                    <div class="p-3 ui-surface-muted-bg border ui-border rounded-lg">
                        <span class="block ui-status-info font-bold mb-1">الاستهلاك بالمتر</span>
                        <p class="ui-text-muted">هو ما يُخصم من المخزون عند بيع الخيار. مثال: 1.5 تعني خصم 1.5 متر، وليست 1.5 رول.</p>
                    </div>
                    <div class="p-3 ui-surface-muted-bg border ui-border rounded-lg">
                        <span class="block ui-status-info font-bold mb-1">السعر والهالك</span>
                        <p class="ui-text-muted">سعر كل عمل يوضع في خيار التجزئة. ونسبة الهالك تُطبق على <strong class="ui-title">كل عملية أو خيار بيع</strong>، فتزيد الأمتار المخصومة وتكلفة المادة المستهلكة، ولا تُحسب مرة واحدة على الرول الكامل.</p>
                    </div>
                </div>

                <div class="mt-4 p-3 ui-status-success-bg border ui-border rounded-lg ui-text-caption ui-status-success leading-6">
                    <i class="fa-solid fa-calculator ui-status-success ml-1"></i>
                    مثال: إذا كان استهلاك «أمامي» <strong>1.5 متر</strong> والهالك <strong>10%</strong>، يخصم النظام <strong>1.65 متر</strong> من المخزون، وتُحسب تكلفة البيع على 1.65 متر. وعند بيع «خلفي» لاحقًا تُطبق النسبة عليه أيضًا.
                </div>
            </div>

            {{-- نظام الأطقم (للعادي فقط) --}}
            <div id="splittable_options_div" class="mb-6 p-5 ui-status-info-bg border ui-border rounded-xl">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-2 ui-status-info-bg rounded-lg ui-status-info"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <div>
                            <h3 class="ui-title font-bold text-sm">نظام البيع كطقم / حبة</h3>
                            <button type="button" data-ui-help-title="شرح نظام الطقم / الحبة" data-ui-help-body="فعّل هذا الخيار إذا كان المنتج يباع كطقم كامل ويمكن أيضًا بيع جزء منه بشكل منفرد." title="شرح" aria-label="شرح نظام الطقم" class="mt-1 inline-flex h-7 w-7 items-center justify-center rounded-full border ui-border ui-status-info-bg ui-text-caption font-black"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_splittable" id="is_splittable" value="1" @checked(old('is_splittable')) class="sr-only peer">
                        <div class="ui-switch-track" aria-hidden="true"></div>
                    </label>
                </div>
                <div id="splittable_fields" class="ui-product-splittable-grid ui-section-divider ui-section-divider-lg">
                    <div class="ui-product-splittable-wide">
                        <label class="block ui-status-info mb-2 ui-text-caption font-bold">نظام البيع</label>
                        <select name="quick_sale_default_unit" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                            <option value="unit" @selected(old('quick_sale_default_unit', 'unit') === 'unit')>طقم (افتراضي)</option>
                            <option value="piece" @selected(old('quick_sale_default_unit') === 'piece')>حبة</option>
                        </select>
                    </div>
                    <div>
                        <label class="block ui-status-info mb-2 ui-text-caption font-bold">عدد الحبات في الطقم</label>
                        <input type="number" name="items_per_unit" id="items_per_unit" value="{{ old('items_per_unit', 4) }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    </div>
                    <div>
                        <label class="block ui-status-info mb-2 ui-text-caption font-bold">سعر الحبة المنفردة</label>
                        <input type="number" step="0.01" name="piece_price" id="piece_price" value="{{ old('piece_price') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    </div>
                </div>
            </div>

            {{-- حقول الأمتار والرولات --}}
            <div id="roll_length_div" class="mb-6 p-4 ui-status-info-bg border ui-border rounded-lg">
                <label class="block ui-status-info mb-2 font-bold italic"><i class="fa-solid fa-ruler-combined ml-1"></i> طول الرول (بالأمتار)</label>
                <input type="number" step="0.01" name="roll_length" id="roll_length" value="{{ old('roll_length', 30) }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
            </div>

            <div id="tint_name_preview" class="hidden mb-4 p-4 ui-status-info-bg border ui-border rounded-xl">
                <div class="mb-4">
                    <p class="ui-status-info font-bold text-sm">بيانات ظهور منتج التضليل</p>
                    <p class="ui-text-caption ui-text-muted mt-1">أدخل الأجزاء منفصلة، وسيكوّن النظام اسم المنتج الأساسي بالترتيب الصحيح تلقائيًا دون الاعتماد على المسافات أو طريقة الكتابة.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="block">
                        <span class="block ui-text-caption font-bold ui-text-muted mb-2">الصنع / النوع</span>
                        <input type="text" name="tint_manufacturer" id="tint_manufacturer" value="{{ old('tint_manufacturer') }}" placeholder="مثال: كوري أو مخلوط" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="block ui-text-caption font-bold ui-text-muted mb-2">الحجم</span>
                        <input type="text" name="tint_size" id="tint_size" value="{{ old('tint_size') }}" placeholder="مثال: كبير أو صغير" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-3 py-2 text-sm">
                        <span class="block mt-1 ui-text-caption ui-text-muted">يُترك حسب تسمية المتجر، وسيظهر كما كتبته.</span>
                    </label>
                    <label class="block">
                        <span class="block ui-text-caption font-bold ui-text-muted mb-2">الدرجة</span>
                        <input type="text" name="tint_grade" id="tint_grade" value="{{ old('tint_grade') }}" placeholder="مثال: 01 أو شفاف" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-3 py-2 text-sm">
                        <span class="block mt-1 ui-text-caption ui-text-muted">يمكن إدخال أي درجة معتمدة لديك.</span>
                    </label>
                    <label class="block">
                        <span class="block ui-text-caption font-bold ui-text-muted mb-2">أخرى <span class="ui-text-muted">(اختياري)</span></span>
                        <input type="text" name="tint_extra" id="tint_extra" value="{{ old('tint_extra') }}" placeholder="مثال: أمريكي" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-3 py-2 text-sm">
                    </label>
                </div>
                <div class="mt-4 p-3 ui-status-success-bg border ui-border rounded-lg text-sm">
                    <span class="ui-status-success">سيظهر المنتج هكذا:</span>
                    <strong id="tint_normalized_name" class="ui-title mr-1">أكمل بيانات الظهور</strong>
                </div>
                <p class="mt-2 ui-text-caption ui-text-muted">مثال: الصنع «صيني» + الحجم «صغير» + الدرجة «01» = <strong class="ui-status-success">صيني صغير 01</strong>.</p>
            </div>

            <div class="mb-6">
                <label class="block ui-text-muted mb-2">اسم المنتج</label>
                <input type="text" name="name" id="product_name" value="{{ old('name') }}" required class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <p id="tint_name_readonly_hint" class="hidden mt-2 ui-text-caption ui-status-info">هذا الحقل يُجمع تلقائيًا من بيانات الظهور أعلاه.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                @php
                    $createMinStockValue = old('min_stock', 1);
                    if (old('min_stock_storage_unit') === 'set') {
                        $createMinStockValue = round((float) $createMinStockValue * max(1, (int) old('items_per_unit', 1)));
                    }
                @endphp
                <input type="hidden" name="min_stock_storage_unit" id="min_stock_storage_unit" value="piece">
                <div>
                    <label class="block ui-text-muted mb-2">سعر البيع</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price') }}" required class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <p id="create_price_unit_hint" class="mt-2 ui-text-caption ui-status-info"></p>
                </div>
                <div>
                    <label class="block ui-text-muted mb-2">سعر التكلفة</label>
                    <input type="number" step="0.01" name="cost_price" value="{{ old('cost_price') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <p id="create_cost_unit_hint" class="mt-2 ui-text-caption ui-status-info"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div id="standard_quantity_div">
                    <label class="block ui-text-muted mb-2">الكمية الابتدائية (وحدات)</label>
                    <input type="number" step="0.01" name="quantity" id="quantity" value="{{ old('quantity') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <p id="create_quantity_unit_hint" class="mt-2 ui-text-caption ui-text-muted"></p>
                </div>
                <div id="fractional_quantity_div">
                    <label class="block ui-status-info mb-2 font-bold">الكمية (عدد الرولات)</label>
                    <input type="number" step="0.01" name="num_rolls" id="num_rolls" value="{{ old('num_rolls') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <p id="create_roll_quantity_hint" class="mt-2 ui-text-caption ui-text-muted"></p>
                </div>
                <div>
                    <label class="block ui-text-muted mb-2">حد المخزون (التنبيه)</label>
                    <input type="number" step="0.01" name="min_stock" id="min_stock" value="{{ $createMinStockValue }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <p id="create_min_stock_unit_hint" class="mt-2 ui-text-caption ui-status-warning"></p>
                </div>
            </div>

            <div id="waste_percentage_div" class="mb-6">
                <label class="block ui-status-info mb-2 font-bold italic">نسبة الهالك %</label>
                <input type="number" step="0.01" name="waste_percentage" value="{{ old('waste_percentage', 0) }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
            </div>

            {{-- خيارات التجزئة --}}
            <div id="fractions_section" class="mb-8 p-6 ui-card">
                <div class="ui-product-section-header mb-3 border-b ui-border pb-2">
                    <h3 class="ui-status-info font-bold flex items-center gap-2"><i class="fa-solid fa-scissors text-sm"></i> خيارات التجزئة</h3>
                    <button type="button" data-product-add-fraction class="ui-btn ui-btn-primary px-3 py-2 ui-text-caption">+ إضافة خيار</button>
                </div>
                <button type="button" data-ui-help-title="شرح خيارات التجزئة" data-ui-help-body="لكل سطر أدخل: <strong>اسم العمل</strong>، ثم <strong>استهلاك عمل واحد بالمتر</strong>، ثم <strong>سعر بيع عمل واحد</strong>." title="تعليمات" aria-label="تعليمات خيارات التجزئة" class="mb-4 inline-flex items-center gap-2 rounded-lg border ui-border ui-status-info-bg px-3 py-1.5 ui-text-caption font-bold"><i class="fa-solid fa-lightbulb"></i> تعليمات</button>
                <div id="fractions_container"></div>
            </div>
            {{-- سعة كرتون التوريد --}}
<div class="mb-6">
    <label class="block ui-text-muted mb-2">
        سعة كرتون التوريد
        <button type="button" data-ui-help-title="شرح سعة كرتون التوريد" data-ui-help-body="سعة الكرتون تعني عدد الحبات داخل الكرتون الواحد. إذا لم يكن المنتج يطلب أو يستلم بالكرتون فاترك الحقل فارغًا." title="شرح" aria-label="شرح سعة الكرتون" class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full border ui-border ui-surface-muted-bg ui-text-caption font-black ui-text-muted"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
    </label>
    <input type="number" name="carton_qty" value="{{ old('carton_qty') }}"
           placeholder="إذا لم يكن المنتج يباع بالكرتون، اتركه فارغاً"
           class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2"
           min="1">
</div>

            <div class="mb-6">
                <label class="block ui-text-muted mb-2">الوصف</label>
                <textarea name="description" rows="3" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <label class="block ui-text-muted mb-2 text-sm">الحالة</label>
                    <select name="status" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                        <option value="active" @selected(old('status') == 'active')>مفعل</option>
                        <option value="inactive" @selected(old('status') == 'inactive')>غير مفعل</option>
                    </select>
                </div>
                <div>
                    <label class="block ui-text-muted mb-2 text-sm">صورة المنتج</label>
                    <input type="file" name="image" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                </div>
            </div>

            {{-- خيار البقاء في الصفحة بعد الحفظ. --}}
            <div class="mb-8 flex items-center gap-3 ui-surface-muted-bg p-4 rounded-lg border ui-border">
                <input type="checkbox" name="stay_here" id="stay_here" value="1" @checked(old('stay_here')) class="w-5 h-5 rounded ui-border ui-surface-muted-bg ui-status-info ">
                <label for="stay_here" class="ui-text-muted text-sm font-medium cursor-pointer">البقاء في هذه الصفحة بعد الإضافة لإنشاء منتج آخر</label>
            </div>

            <button type="submit" class="w-full ui-status-info-bg ui-title font-bold py-4 rounded-lg transition shadow-lg active:scale-95">
                <i class="fa-solid fa-save ml-2"></i> حفظ المنتج
            </button>
        </form>
    </div>
</div>

@if ($errors->any())
    <div class="hidden" data-product-form-errors="{{ json_encode($errors->all(), JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endif


{{-- علامة تفعيل لوحدة إنشاء المنتج؛ تحويلات الوحدات والحد الأدنى بقيت كما كانت. --}}
<div class="hidden" data-product-create-interface aria-hidden="true"></div>
@endsection
