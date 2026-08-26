@extends('dashboard.app')

@section('title', 'تعديل المنتج – ' . $product->name)

@section('content')

{{-- الخميس 30-7 --}}
<div class="ui-product-form-page" data-store-product-form>

    {{-- تعرض أخطاء الحفظ عبر SweetAlert في الأسفل لتبقى الرسائل عربية وواضحة. --}}

    {{-- الهيدر --}}
    <div class="ui-product-form-header">
        <a href="{{ route('user.stores.products.index', $store->id) }}"
           class="ui-product-form-back inline-flex items-center gap-2 px-4 py-2 rounded-lg ui-surface-muted-bg border ui-border ui-text-muted ui-hover-info transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع إلى المنتجات</span>
        </a>

        <h1 class="ui-product-form-title text-2xl font-bold ui-title">
            تعديل المنتج – {{ $product->name }}
        </h1>
    </div>

    <div class="ui-product-form-card ui-surface-muted-bg border ui-border rounded-xl">

        <form action="{{ route('user.stores.products.update', [$store->id, $product->id]) }}"
              method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @php
                $mainCategories = $categories->where('is_main_category', 1);
                $normalCategories = $categories->where('is_main_category', 0);
            @endphp

            {{-- القسم --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">القسم</label>
                <select name="category_id" id="category_id" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    @if($mainCategories->isNotEmpty())
                        <optgroup label="الأنشطة">
                            @foreach($mainCategories as $category)
                                <option value="{{ $category->id }}" data-category-name="{{ $category->name }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if($normalCategories->isNotEmpty())
                        <optgroup label="الأقسام">
                            @foreach($normalCategories as $category)
                                <option value="{{ $category->id }}" data-category-name="{{ $category->name }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
            </div>

            {{-- نوع المنتج --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2 font-bold ui-status-info">نوع المنتج</label>
                <select name="product_type" id="product_type"
                        class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <option value="standard" @selected(old('product_type', $product->product_type) == 'standard')>منتج عادي (بالحبة)</option>
                    <option value="fractional" @selected(old('product_type', $product->product_type) == 'fractional')>منتج قابل للتجزئة (رول/قص)</option>
                </select>
            </div>

            {{-- خيار استخدام المنتج: افتراضيًا للبيع، ويمكن جعله مشتريات للمالك لإخفائه من البيع والجرد والحسابات. --}}
            <div class="mb-6 p-4 ui-surface-muted-bg border ui-border rounded-xl">
                <div class="mb-2 flex items-center gap-2">
                    <label class="ui-title font-bold">استخدام المنتج</label>
                    <x-ui.help title="شرح استخدام المنتج" body="عند اختيار مشتريات للمالك سيبقى المنتج مخفيًا من البيع والبحث وبطاقات المنتجات والمنخفضة والجرد والحسابات، ويظهر في إدارة المنتجات فقط عند تفعيل زر إظهاره وفي التوريد." />
                </div>
                <select name="usage_type" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <option value="sale" @selected(old('usage_type', $product->usage_type ?? 'sale') === 'sale')>للبيع (افتراضي)</option>
                    <option value="owner_purchase" @selected(old('usage_type', $product->usage_type ?? 'sale') === 'owner_purchase')>مشتريات للمالك فقط</option>
                </select>
            </div>

            {{-- إرشادات منتج الرول؛ تتغير حسب القسم المختار وتظهر فقط للرول/القص. --}}
            <div id="fractional_product_guidance" class="mb-6 ui-status-info-bg border ui-border rounded-xl overflow-hidden">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-5 ui-status-info font-bold">
                        <span><i class="fa-solid fa-circle-info ml-1"></i> إرشادات منتج الرول/القص</span>
                        <span class="ui-text-caption ui-text-muted group-open:hidden">عرض التفاصيل</span>
                        <span class="hidden ui-text-caption ui-text-muted group-open:inline">إخفاء التفاصيل</span>
                    </summary>
                    <div class="px-5 pb-5">
                <div class="flex items-start gap-3 mb-4">
                    <div class="shrink-0 w-10 h-10 rounded-lg ui-status-info-bg ui-status-info flex items-center justify-center">
                        <i class="fa-solid fa-circle-info"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2"><h3 id="fractional_guidance_title" class="ui-status-info font-bold">دليل إدخال منتج رول/قص</h3><x-ui.help title="تعليمات منتج الرول/القص" body="سجّل كل رول فعلي كمنتج مستقل. أدخل طول الرول الكامل بالمتر، وتكلفة الرول الكامل، ثم أضف خيارات القص مع الاستهلاك بالمتر وسعر البيع." /></div>
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
                </details>
            </div>

            {{-- نظام الأطقم (يظهر فقط للمنتج العادي) --}}
<div id="splittable_options_div" class="mb-6 p-4 ui-surface-muted-bg border ui-border rounded-lg">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <label for="is_splittable" class="ui-title font-medium">تفعيل نظام البيع كطقم / حبة</label>
            <x-ui.help variant="warning" title="تنبيه نظام الطقم / الحبة" body="تغيير المنتج من حبة إلى طقم أو العكس لا يعيد كتابة سجل حركات المخزون القديم. إذا احتجت تعديل الرصيد بعد تغيير النظام استخدم صفحة إدارة المخزون حتى تبقى الحركات القديمة بوحدتها الأصلية." />
        </div>

        <label class="relative inline-flex items-center cursor-pointer" title="تفعيل أو إيقاف نظام البيع كطقم وحبة">
            <input type="checkbox" name="is_splittable" id="is_splittable" value="1"
                   @checked(old('is_splittable', $product->is_splittable))
                   class="sr-only peer" role="switch" aria-label="تفعيل نظام البيع كطقم وحبة">
            <div class="ui-switch-track" aria-hidden="true"></div>
        </label>
    </div>

    <div id="splittable_fields" class="ui-product-splittable-grid">
        <div class="ui-product-splittable-wide">
            <label class="block ui-text-muted mb-2 text-sm">نظام البيع</label>
            <select name="quick_sale_default_unit" class="w-full ui-surface-muted-bg border ui-border ui-title rounded px-3 py-2">
                <option value="unit" @selected(old('quick_sale_default_unit', $product->quick_sale_default_unit ?? 'unit') === 'unit')>طقم (افتراضي)</option>
                <option value="piece" @selected(old('quick_sale_default_unit', $product->quick_sale_default_unit ?? 'unit') === 'piece')>حبة</option>
            </select>
        </div>
        <div>
            <label class="block ui-text-muted mb-2 text-sm">عدد الحبات في الطقم</label>
            <input type="number" name="items_per_unit" id="items_per_unit" min="1" value="{{ old('items_per_unit', $product->items_per_unit) }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded px-3 py-2">
        </div>
        <div>
            <label class="block ui-text-muted mb-2 text-sm">سعر الحبة المنفردة</label>
            <input type="number" step="0.01" name="piece_price" value="{{ old('piece_price', $product->piece_price) }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded px-3 py-2">
        </div>
    </div>
</div>
            {{-- طول الرول (يظهر فقط للمجزأ) --}}
            <div id="roll_length_div" class="mb-6">
                <label class="block ui-status-info mb-2 font-bold">طول الرول الكامل (بالأمتار)</label>
                <input type="number" step="0.01" name="roll_length" value="{{ old('roll_length', $product->roll_length) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
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

            {{-- الاسم --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">اسم المنتج</label>
                <input type="text" name="name" id="product_name" value="{{ old('name', $product->name) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <p id="tint_name_readonly_hint" class="hidden mt-2 ui-text-caption ui-status-info">هذا الحقل يُجمع تلقائيًا من بيانات الظهور أعلاه.</p>
            </div>

            {{-- سعر البيع --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">سعر البيع</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <p id="price_unit_hint" class="mt-2 ui-text-caption ui-status-info"></p>
            </div>

            {{-- سعر التكلفة --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">سعر التكلفة</label>
                <input type="number" step="0.01" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <p id="cost_unit_hint" class="mt-2 ui-text-caption ui-status-warning font-bold"></p>
            </div>

            {{-- الحد الأدنى للمخزون --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">الحد الأدنى للمخزون</label>
                <input type="number" step="1" min="0" name="min_stock" id="min_stock" value="{{ old('min_stock', $product->min_stock) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <p id="min_stock_unit_hint" class="mt-2 ui-text-caption ui-status-warning"></p>
            </div>
 {{-- سعة كرتون التوريد (الحقل الجديد) --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">
                    سعة كرتون التوريد
                    <x-ui.help title="شرح سعة كرتون التوريد" body="سعة الكرتون تعني عدد الحبات داخل الكرتون الواحد. إذا لم يكن المنتج يطلب أو يستلم بالكرتون فاترك الحقل فارغًا." />
                </label>
                <input type="number" name="carton_qty" value="{{ old('carton_qty', $product->carton_qty) }}"
                       placeholder="إذا لم يكن المنتج يباع بالكرتون، اتركه فارغاً"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2"
                       min="1">
           </div>
            {{-- نسبة الهالك --}}
            <div class="mb-6" id="waste_percentage_div">
                <label class="block ui-status-info mb-2">نسبة الهالك %</label>
                <input type="number" step="0.01" name="waste_percentage" value="{{ old('waste_percentage', $product->waste_percentage) }}"
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
            </div>

            {{-- خيارات التجزئة --}}
            <div id="fractions_section" class="mb-6 p-4 ui-surface-muted-bg rounded-lg">
                <div class="ui-product-section-header mb-4">
                    <h3 class="ui-status-info font-bold">خيارات التجزئة الحالية</h3>
                    <button type="button" data-product-add-fraction class="ui-btn ui-btn-primary px-3 py-2 ui-text-caption">+ إضافة خيار جديد</button>
                </div>
                {{-- قيمة deduction_value هنا محفوظة كاستهلاك فعلي بالمتر لكل خيار رول، وليست نسبة من طول الرول. --}}
                <div class="mb-4"><x-ui.help title="شرح خيارات التجزئة" body="لكل سطر أدخل: <strong>اسم العمل</strong>، ثم <strong>استهلاك عمل واحد بالمتر</strong>، ثم <strong>سعر بيع عمل واحد</strong>." /></div>
                <div id="fractions_container">
                    @php
                        $data = old('fractions') ?? $product->fractions()->get()->toArray();
                    @endphp
                    @foreach($data as $index => $item)
                        @php $item = (array) $item; @endphp
                        <div class="ui-product-fraction-row" id="row_{{ $index }}">
                            <input type="text" name="fractions[{{ $index }}][option_label]" value="{{ $item['option_label'] ?? '' }}" placeholder="الاسم" class="ui-product-fraction-main ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                            <input type="number" step="0.01" name="fractions[{{ $index }}][deduction_value]" value="{{ $item['deduction_value'] ?? '' }}" placeholder="الاستهلاك بالمتر" class="ui-product-fraction-compact-field ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                            <input type="number" step="0.01" name="fractions[{{ $index }}][price]" value="{{ $item['price'] ?? '' }}" placeholder="السعر" class="ui-product-fraction-compact-field ui-surface-muted-bg border ui-border ui-title rounded px-2 py-1 text-sm">
                            <button type="button" data-product-remove-fraction class="ui-product-fraction-remove ui-status-danger px-2 font-bold">×</button>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- الكمية الحالية --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">الكمية الحالية</label>
                <div class="w-full ui-surface-muted-bg border ui-border ui-text-muted rounded-lg px-4 py-2">
                    {{ \App\Support\ProductQuantityFormatter::currentStock($product) }}
                </div>
                <a href="{{ route('user.stores.products.stock', [$store->id, $product->id]) }}" class="inline-block mt-2 ui-status-info text-sm">إدارة المخزون</a>
            </div>

            {{-- الوصف --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">الوصف</label>
                <textarea name="description" rows="4" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">{{ old('description', $product->description) }}</textarea>
            </div>

            {{-- الحالة --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">الحالة</label>
                <select name="status" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                    <option value="active" @selected($product->status == 'active')>مفعل</option>
                    <option value="inactive" @selected($product->status == 'inactive')>غير مفعل</option>
                </select>
            </div>

            {{-- الصورة --}}
            <div class="mb-6">
                <label class="block ui-text-muted mb-2">صورة المنتج</label>
                <input type="file" name="image" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg px-4 py-2">
                <img src="{{ $product->image ? asset('storage/' . $product->image) : asset('images/default-product.png') }}" class="w-32 h-32 object-cover rounded-lg mt-3 border ui-border">
            </div>

            <button type="submit" class="ui-status-info-bg ui-title px-6 py-2 rounded-lg transition">
                <i class="fa-solid fa-save ml-1"></i> حفظ التعديلات
            </button>
        </form>
    </div>
</div>

@if ($errors->any())
    <div class="hidden" data-product-form-errors="{{ json_encode($errors->all(), JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endif


{{-- علامة تفعيل لوحدة تعديل المنتج مع عدد أسطر التجزئة الحالي فقط. --}}
<div class="hidden" data-product-edit-interface data-fraction-index="{{ count(old('fractions') ?? $product->fractions) }}" aria-hidden="true"></div>
@endsection
