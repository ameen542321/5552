@extends('dashboard.app')

@section('title', 'البيع السريع المطوّر')

@section('content')
<div x-data="quickSale()" x-init="init()" @tint-items-ready.window="addTintItemsToCart($event.detail)" class="mx-auto max-w-6xl px-1.5 py-2 text-right sm:px-4 sm:py-3 md:px-6 md:py-4 xl:px-8" dir="rtl">

    {{-- 🔥 الشريط العلوي --}}
    <div class="flex flex-col md:flex-row items-center justify-between ui-surface-muted-bg border ui-border px-4 py-4 rounded-2xl mb-3 gap-4 shadow-xl">
        <div class="flex items-center gap-4 w-full md:w-auto justify-between md:justify-start">
            <a href="{{ route('accountant.dashboard') }}" class="ui-btn ui-btn-secondary px-4 py-2 text-sm">← رجوع</a>
            <h1 class="text-lg md:text-xl font-bold ui-title">تسجيل بيع جديد</h1>
        </div>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto">
            <div class="ui-text-muted text-sm ui-surface-muted-bg px-4 py-2 rounded-lg border ui-border text-center font-sans">
                المحاسب: <span class="font-bold ui-status-info">{{ auth('accountant')->user()->name }}</span>
            </div>
        </div>
    </div>

    @if($errors->any() || session('error'))
        <div class="mb-4 rounded-2xl border ui-border ui-status-danger-bg px-4 py-3 text-sm ui-status-danger shadow-lg">
            <div class="mb-1 font-black"><i class="fa-solid fa-circle-exclamation ml-1"></i>تعذر إتمام البيع</div>
            @if(session('error'))
                <div>{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="space-y-6">

        {{-- مساحة العمل الرئيسية: بحث، تضليل، وسلة. تتحول إلى مساحة أعرض في الشاشات الكبيرة بدل تمدد عشوائي. --}}
        <div class="space-y-3">
            @if($hasAvailableTintProducts)
            @if(!empty($latestShiftOperation))

                <div class="overflow-hidden rounded-2xl border ui-border ui-status-info-bg px-3 py-2 shadow-lg " aria-label="آخر عملية مسجلة ضمن الشفت الحالي">
                    <div class="flex items-center gap-3 whitespace-nowrap ui-text-caption font-bold ui-status-info quick-sale-marquee-track sm:text-sm">
                        <span class="rounded-full ui-status-info-bg px-2 py-1 ui-text-caption font-black ui-status-info">آخر عملية في الشفت</span>
                        <span>#{{ $latestShiftOperation['id'] }}</span>
                        <span>الوقت: {{ $latestShiftOperation['time'] }}</span>
                        <span>الدفع: {{ $latestShiftOperation['payment_type'] }}</span>
                        <span>المدفوع: {{ number_format($latestShiftOperation['paid_amount'], 2) }} ر.س</span>
                        <span>الإجمالي: {{ number_format($latestShiftOperation['final_total'], 2) }} ر.س</span>
                        <span>البيان: {{ $latestShiftOperation['summary'] }}</span>
                        @if(!empty($latestShiftOperation['description']) && $latestShiftOperation['description'] !== $latestShiftOperation['summary'])
                            <span>الوصف: {{ $latestShiftOperation['description'] }}</span>
                        @endif
                    </div>
                </div>
            @endif
            {{-- يظهر زر التضليل فقط عند وجود رول تضليل متوفر وله خيارات تجزئة. --}}
            <div class="rounded-2xl border ui-border ui-card   p-3 shadow-lg sm:p-4">
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-tint-sale-modal'))"
                        class="flex w-full items-center justify-between gap-3 rounded-xl border ui-border ui-status-info-bg px-4 py-3 text-right ui-title shadow-lg transition active:scale-[0.99] sm:py-4">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ui-surface-strong-bg text-xl">◩</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-black sm:text-base">تضليل</span>
                            <span class="mt-0.5 block ui-text-caption ui-status-info sm:ui-text-caption">إضافة عملية تضليل سريعة إلى السلة</span>
                        </span>
                    </span>
                    <span class="shrink-0 text-lg" aria-hidden="true">←</span>
                </button>
            </div>
            @endif

            {{-- البحث المرن مع زر التوضيح المعتمد --}}
            <div class="ui-surface-muted-bg border ui-border p-4 rounded-2xl shadow-lg relative" @click.outside="closeSearchResults()">
                <div class="flex items-center justify-between mb-1">
                    <label class="ui-text-muted ui-text-caption mb-2 block font-bold italic">ابحث عن منتج</label>
                    <button type="button" data-ui-help-title="البحث عن منتج" data-ui-help-body="يمكنك البحث باسم المنتج. اترك البحث فارغاً لإظهار البطاقات السريعة، أو اكتب حرفاً/كلمة لإظهار نتائج البحث فقط." class="ui-help-btn"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <input type="text" x-model="search" x-ref="searchInput" @focus="openSearchResults()" @input.debounce.200ms="searchProducts"
                       placeholder="اكتب اسم المنتج..."
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-4   outline-none transition-all text-right font-bold">

                {{-- البطاقات السريعة تظهر فقط عندما يكون البحث فارغاً --}}
                <div x-show="showingFeatured && results.length > 0" class="mt-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="ui-text-caption ui-text-muted font-bold">آخر 4 منتجات مباعة</span>
                        <span class="ui-text-caption ui-text-muted">بطاقات سريعة وبسيطة</span>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <template x-for="product in results.slice(0, 4)" :key="'top-' + product.id + '-' + product.price + '-' + product.piece_price + '-' + (product.price_updated_at || '')">
                            <button type="button"
                                    @click="addToCart(product)"
                                    :disabled="product.is_out_of_stock"
                                    :class="product.is_out_of_stock ? 'ui-status-danger-bg ui-border' : (product.is_low_stock ? 'ui-status-warning-bg ui-border' : 'ui-surface-muted-bg ui-border')"
                                    class="w-full p-2.5 rounded-lg border text-right transition shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="ui-title font-bold text-[13px] truncate" x-text="product.name"></span>
                                            <span x-show="!product.is_out_of_stock && !product.is_low_stock" class="ui-status-success-bg ui-status-success ui-text-caption px-1.5 py-0.5 rounded-md font-black border ui-border">متوفر</span>
                                            <span x-show="product.is_low_stock && !product.is_out_of_stock" class="ui-status-warning-bg ui-status-warning ui-text-caption px-1.5 py-0.5 rounded-md font-black border ui-border">منخفض</span>
                                            <span x-show="product.is_out_of_stock" class="ui-status-danger-bg ui-title ui-text-caption px-1.5 py-0.5 rounded-md font-black">منتهي</span>
                                        </div>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 ui-text-caption">
                                            <span class="ui-text-muted">المخزون:</span>
                                            <span :class="product.is_out_of_stock ? 'ui-status-danger' : (product.is_low_stock ? 'ui-status-warning' : 'ui-status-success')"
                                                  class="font-bold font-sans"
                                                  x-text="product.display_quantity"></span>
                                            <span class="ui-text-muted" x-text="product.display_unit"></span>
                                        </div>
                                    </div>

                                    <div class="text-left shrink-0">
                                        <span class="ui-status-info font-black block font-sans text-[13px]" x-text="displayPriceLabel(product)"></span>
                                        <span class="ui-text-caption block mt-0.5"
                                              :class="product.is_out_of_stock ? 'ui-status-danger' : 'ui-text-muted'"
                                              x-text="product.is_out_of_stock ? 'غير متاح' : 'إضافة مباشرة'"></span>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="searchResultsOpen && results.length === 0" class="mt-4 ui-surface-muted-bg border border-dashed ui-border rounded-xl px-4 py-8 text-center">
                    <div class="text-3xl mb-2">🔎</div>
                    <p class="ui-title font-bold text-sm">لا توجد نتائج مطابقة حالياً.</p>
                    <p class="ui-text-muted ui-text-caption mt-1">جرّب اسمًا آخر أو اترك البحث فارغًا لعرض المنتجات الأكثر بيعًا.</p>
                </div>

                {{-- بقية النتائج المطورة --}}
                <div x-show="searchResultsOpen && !showingFeatured && results.length > 0" x-cloak class="absolute z-50 left-0 right-0 mt-2 ui-card shadow-2xl max-h-72 overflow-y-auto">
                    <div class="px-3 py-2 ui-text-caption ui-text-muted border-b ui-border ui-surface-muted-bg">نتائج البحث فقط</div>
                    <template x-for="product in results" :key="product.id + '-' + product.price + '-' + product.piece_price + '-' + (product.price_updated_at || '')">
                        <div @click="addToCart(product)"
                             class="p-3 sm:p-4 border-b ui-border transition group text-right"
                             :class="product.is_out_of_stock ? 'cursor-not-allowed opacity-70 ui-status-danger-bg' : ((product.is_low_stock ? 'ui-status-warning-bg' : 'ui-surface-muted-bg') + ' cursor-pointer')">

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="ui-title font-bold block group-ui-status-info text-sm sm:text-base truncate" x-text="product.name"></span>
                                        <span x-show="!product.is_out_of_stock && !product.is_low_stock" class="ui-status-success-bg ui-status-success ui-text-caption px-2 py-0.5 rounded-md font-black border ui-border">متوفر</span>
                                        <span x-show="product.is_low_stock && !product.is_out_of_stock" class="ui-status-warning-bg ui-status-warning ui-text-caption px-2 py-0.5 rounded-md font-black border ui-border">كمية منخفضة</span>
                                        <span x-show="product.is_out_of_stock" class="ui-status-danger-bg ui-title ui-text-caption px-2 py-0.5 rounded-md font-black animate-pulse">منتهي</span>
                                        <template x-if="product.is_splittable">
                                            <span class="ui-status-info-bg ui-status-info ui-text-caption px-2 py-0.5 rounded-md font-bold border ui-border">يدعم الحبة</span>
                                        </template>
                                        <template x-if="product.product_type === 'fractional'">
                                            <span class="ui-status-warning-bg ui-status-warning ui-text-caption px-2 py-0.5 rounded-md font-bold border ui-border">رول / متر</span>
                                        </template>
                                    </div>

                                    <p class="ui-text-muted ui-text-caption mt-1 leading-5 break-words" x-text="product.description || 'لا يوجد وصف'"></p>

                                    <div class="mt-2 ui-text-caption">
                                        <div class="ui-surface-muted-bg rounded-lg px-3 py-2 border ui-border inline-flex flex-wrap items-center gap-1">
                                            <span class="ui-text-muted">المخزون الحالي:</span>
                                            <span :class="product.is_out_of_stock ? 'ui-status-danger' : (product.is_low_stock ? 'ui-status-warning' : 'ui-status-success')"
                                                  class="font-bold font-sans"
                                                  x-text="product.display_quantity"></span>
                                            <span class="ui-text-muted" x-text="product.display_unit"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="sm:text-left sm:min-w-[120px]">
                                    <span class="ui-status-info font-black block font-sans text-base sm:text-lg" x-text="displayPriceLabel(product)"></span>
                                    <template x-if="product.product_type === 'fractional' && product.meter_price">
                                        <span class="ui-text-caption ui-status-info block mt-1" x-text="'سعر المتر: ' + product.meter_price + ' ر.س'"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- السلة مع زر التوضيح المعتمد --}}
            <div x-show="cart.length > 0" x-cloak x-transition class="ui-surface-muted-bg border ui-border p-4 rounded-2xl shadow-lg">
                <div class="flex items-center justify-between mb-4 border-b ui-border pb-2">
                    <h2 class="ui-title font-bold flex items-center gap-2 text-right">🛒 قائمة البيع</h2>
                    <button type="button" data-ui-help-title="قائمة البيع" data-ui-help-body="اضغط + أو - لتغيير الكمية. منتجات الرول والتضليل تحفظ كسطور مستقلة لحماية حساب المخزون والتكلفة." class="ui-help-btn"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                </div>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div x-show="cart.length === 0" class="lg:col-span-2 ui-surface-muted-bg border border-dashed ui-border rounded-xl px-4 py-8 text-center">
                        <div class="text-3xl mb-2">🧺</div>
                        <p class="ui-title font-bold text-sm">السلة فارغة حالياً</p>
                        <p class="ui-text-muted ui-text-caption mt-1">ابحث عن منتج أو اختر من المنتجات الأكثر بيعًا لإضافته بسرعة.</p>
                    </div>
                    <template x-for="(item, index) in cart" :key="item.temp_id">
                        <div x-show="!item.tint_group_id || isFirstTintGroupItem(item, index)" class="flex flex-col ui-surface-muted-bg p-3 rounded-xl border ui-border gap-2 text-right">
                            <template x-if="item.tint_group_id && isFirstTintGroupItem(item, index)">
                                <div class="space-y-3 rounded-xl border ui-border ui-status-info-bg px-3 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="block text-sm font-black ui-status-info" x-text="item.tint_group_label"></span>
                                            <span class="mt-1 block text-xl font-black ui-status-success" x-text="Math.round(tintGroupTotal(item.tint_group_id)) + ' ر.س'"></span>
                                        </div>
                                        <div class="flex shrink-0 gap-2">
                                            <button type="button" @click="toggleTintGroupDetails(item.tint_group_id)" class="ui-btn ui-btn-info px-3 py-2 ui-text-caption" x-text="isTintGroupExpanded(item.tint_group_id) ? 'إخفاء التفاصيل' : 'التفاصيل'"></button>
                                            <button type="button" @click="removeTintGroup(item.tint_group_id)" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">حذف</button>
                                        </div>
                                    </div>
                                    <div x-show="isTintGroupExpanded(item.tint_group_id)" x-transition class="space-y-2 ui-section-divider ui-section-divider-sm">
                                        <template x-for="detail in tintGroupDetails(item.tint_group_id)" :key="detail.key">
                                            <div class="flex items-start justify-between gap-3 rounded-lg ui-surface-muted-bg px-3 py-2">
                                                <div class="min-w-0">
                                                    <span class="block ui-text-caption font-black ui-title" x-text="detail.label"></span>
                                                    <span class="mt-0.5 block ui-text-caption ui-text-muted" x-text="detail.product + ' — ' + detail.registration"></span>
                                                </div>
                                                <span class="shrink-0 ui-text-caption font-black ui-status-success" x-text="Math.round(detail.price) + ' ر.س'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <div x-show="!item.tint_group_id" class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <div class="min-w-0">
                                            <p class="ui-title font-bold text-sm" x-text="item.tint_component_label || item.name"></p>
                                            <p x-show="item.tint_group_id" class="mt-0.5 truncate ui-text-caption ui-text-muted" x-text="item.name"></p>
                                        </div>
                                    </div>

                                    {{-- خيار البيع (طقم أو حبة) --}}
                                    <template x-if="item.is_splittable && !item.is_fractional">
                                        <div class="mt-2 w-48">
                                            <select x-model="item.sale_unit" @change="updateSplittablePrice(item)" @wheel="preventWheelChange($event)"
                                                    class="w-full ui-surface-muted-bg border ui-border ui-status-info ui-text-caption rounded-lg px-2 py-1.5 outline-none">
                                                <option value="unit">البيع بـ (طقم كامل)</option>
                                                <option value="piece">البيع بـ (حبة منفردة)</option>
                                            </select>
                                        </div>
                                    </template>

                                    {{-- خيارات التجزئة (للمنتجات الرول) --}}
                                    <template x-if="item.is_fractional && !item.tint_group_id">
                                        <div class="space-y-2 mt-2">
                                            <select x-model="item.fraction_id" @change="updateFractionPrice(item)" @wheel="preventWheelChange($event)"
                                                    class="w-full ui-surface-muted-bg border ui-border ui-text-muted ui-text-caption rounded-lg px-2 py-2 outline-none">
                                                <option value="0">— اختر نوع التجزئة —</option>
                                                <template x-for="fr in item.available_fractions" :key="fr.id">
                                                    <option :value="fr.id" x-text="fr.option_label + ' (' + Math.round(fr.price) + ' ر.س)'"></option>
                                                </template>
                                                <option value="custom">✨ مخصص (إدخال يدوي)</option>
                                            </select>

                                            <div x-show="item.fraction_id === 'custom'" class="grid grid-cols-3 gap-2 p-2 ui-surface-muted-bg rounded-lg border ui-border">
                                                <div>
                                                    <label class="ui-text-caption ui-status-info block mb-1">حدد العمل</label>
                                                    <input type="text" x-model="item.custom_name" class="w-full ui-surface-muted-bg border ui-border ui-title ui-text-caption px-2 py-1 rounded">
                                                </div>
                                                <div>
                                                    <label class="ui-text-caption ui-status-info block mb-1">السعر</label>
                                                    <input type="number" x-model.number="item.price" @input="item.total = item.quantity * item.price" @wheel="preventWheelChange($event)" class="w-full ui-surface-muted-bg border ui-border ui-title ui-text-caption px-2 py-1 rounded">
                                                </div>
                                                <div>
                                                    <label class="ui-text-caption ui-status-warning block mb-1">الأمتار</label>
                                                    <input type="number" step="0.01" x-model.number="item.custom_consumption" @wheel="preventWheelChange($event)" class="w-full ui-surface-muted-bg border ui-border ui-title ui-text-caption px-2 py-1 rounded">
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <button x-show="!item.tint_group_id" @click="removeItem(item)" class="inline-flex h-10 w-10 items-center justify-center ui-status-danger" aria-label="حذف العنصر">🗑️</button>
                            </div>
                            <div x-show="!item.tint_group_id" class="flex items-center justify-between ui-section-divider ui-section-divider-sm">
                                <div x-show="!item.is_fractional" class="flex items-center ui-surface-muted-bg rounded-lg p-1 border ui-border">
                                    <button @click="decrease(item)" class="w-8 h-8 ui-title ui-surface-muted-bg rounded-md font-bold">-</button>
                                    <span class="w-10 text-center ui-title font-black text-lg" x-text="item.quantity"></span>
                                    <button @click="increase(item)" class="w-8 h-8 ui-title ui-surface-muted-bg rounded-md font-bold">+</button>
                                </div>
                                <div x-show="item.is_fractional" class="ui-text-caption ui-status-warning-bg border ui-border rounded-lg px-3 py-2">
                                    خيار الرول يباع كسطر مستقل
                                </div>
                                <div class="ui-status-success font-black text-xl" x-text="Math.round(item.total) + ' ر.س'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- لوحة التحكم: إجماليات ودفع وتأكيد. تظهر كبطاقة عملية بجانب مساحة العمل في الشاشات الكبيرة. --}}
        <div class="space-y-6">
            <div class="ui-surface-muted-bg border ui-border p-4 rounded-2xl shadow-lg sm:p-5">
                <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="ui-card p-3 sm:col-span-2">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="ui-title font-black text-sm">بيانات الفاتورة</h3>
                            <button type="button" data-ui-help-title="بيانات الفاتورة" data-ui-help-body="أدخل الأجور والضريبة والملاحظات قبل اختيار طريقة الدفع واعتماد العملية." class="ui-help-btn"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="rounded-xl border ui-border ui-surface-muted-bg p-3 sm:col-span-2">
                        <label class="ui-text-muted ui-text-caption font-bold block mb-1 pr-1 italic text-right">🛠️ أجور اليد (التركيب)</label>
                        <input type="number" step="1" x-model.number="labor_total" @focus="$event.target.select()" @wheel="preventWheelChange($event)" placeholder="أدخل أجور اليد" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-xl text-center font-black outline-none shadow-inner placeholder:text-sm placeholder:font-bold sm:text-2xl">

                        <div x-show="labor_total > 0" x-transition class="ui-section-divider ui-section-divider-sm">
                            <label class="ui-text-muted ui-text-caption font-bold block mb-1 pr-1 italic text-right">📝 وصف العمل / ملاحظات</label>
                            {{-- أزرار جاهزة لتسريع كتابة وصف عمل اليد بدون التأثير على الإدخال اليدوي --}}
                            <div class="flex flex-wrap gap-2 mb-2">
                                <template x-for="option in laborDescriptionOptions" :key="option">
                                    <button type="button"
                                            @click="appendLaborDescription(option)"
                                            class="ui-quick-sale-preset ui-text-caption transition">
                                        <span x-text="option"></span>
                                    </button>
                                </template>
                            </div>
                            <textarea x-model="description" placeholder="وصف سريع للعمل..." class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-sm outline-none text-right font-bold" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="rounded-xl border ui-border ui-surface-muted-bg p-3 sm:col-span-2">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="ui-text-muted ui-text-caption font-bold block mb-1 pr-1 italic text-right">⚖️ نسبة الضريبة</label>
                                <select x-model.number="tax_rate" @wheel="preventWheelChange($event)" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 font-black outline-none text-center">
                                    <option value="0">بدون ضريبة (0%)</option>
                                    <option value="15">ضريبة (15%)</option>
                                </select>
                            </div>
                            <div class="hidden rounded-xl border ui-border ui-status-warning-bg px-3 py-2 ui-text-caption font-bold leading-6 ui-status-warning md:flex md:items-center">
                                تنبيه: عند اختيارك لقيمة ضريبة وانت لا تمتلك رقما ضريبيا معتمدا فانت تعرض نفسك للمسالة القانونية
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 ui-card p-4 sm:col-span-2">
                        {{-- أزرار أنواع الدفع --}}
                        <div class="flex items-center justify-between mb-2">
                            <label class="ui-text-muted ui-text-caption font-black">اختر نوع الدفع</label>
                            <span x-show="sale_type" class="ui-text-caption ui-status-success font-bold" x-text="'تم الاختيار: ' + (sale_type === 'cash' ? 'كاش' : sale_type === 'card' ? 'شبكة' : sale_type === 'mixed' ? 'مختلط' : 'آجل')"></span>
                        </div>
                        <div class="grid grid-cols-4 gap-2" :class="!sale_type ? 'ui-border rounded-2xl p-1' : ''">
                            <button type="button" @click="setPaymentType('cash')"
                                    :class="sale_type === 'cash' ? 'is-active scale-105' : ''"
                                    class="ui-quick-sale-payment ui-text-caption transition-all"><span class="ui-quick-sale-payment-icon"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i></span><span>كاش</span></button>

                            <button type="button" @click="setPaymentType('card')"
                                    :class="sale_type === 'card' ? 'is-active scale-105' : ''"
                                    class="ui-quick-sale-payment ui-text-caption transition-all"><span class="ui-quick-sale-payment-icon"><i class="fa-solid fa-credit-card" aria-hidden="true"></i></span><span>شبكة</span></button>

                            <button type="button" @click="setPaymentType('mixed')"
                                    :class="sale_type === 'mixed' ? 'is-active scale-105' : ''"
                                    class="ui-quick-sale-payment ui-text-caption transition-all"><span class="ui-quick-sale-payment-icon"><i class="fa-solid fa-code-branch" aria-hidden="true"></i></span><span>مختلط</span></button>

                            <button type="button" @click="setPaymentType('credit')"
                                    :class="sale_type === 'credit' ? 'is-active scale-105' : ''"
                                    class="ui-quick-sale-payment ui-text-caption transition-all"><span class="ui-quick-sale-payment-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span><span>آجل</span></button>
                        </div>
                        <p x-show="!sale_type" class="ui-text-caption ui-status-danger font-bold text-center">يجب اختيار نوع الدفع قبل تأكيد العملية.</p>

                        <div x-show="sale_type && sale_type !== 'credit' && sale_type !== 'mixed'"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2">
                            <label class="ui-text-muted ui-text-caption font-bold block mb-1 pr-1 italic text-right">💵 المبلغ المستلم</label>
                            <input type="number" step="1" x-model.number="paid_amount" @focus="$event.target.select()" @wheel="preventWheelChange($event)" placeholder="أدخل المبلغ المستلم" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-xl text-center font-black outline-none shadow-inner placeholder:text-sm placeholder:font-bold sm:text-2xl">
                            <div x-show="hasPartialCredit" x-transition class="mt-3 space-y-1">
                                <label class="ui-text-muted ui-text-caption font-bold block pr-1 italic text-right">🧮 إجمالي تغطية العملية</label>
                                <input type="number" step="1" :value="Math.round(operationCoverage)" readonly :class="operationCoverage < final_total ? 'ui-border ui-status-danger' : 'ui-border ui-status-success'" class="w-full ui-surface-muted-bg rounded-xl border px-4 py-3 text-center font-black outline-none shadow-inner cursor-not-allowed">
                            </div>
                        </div>

                        {{-- ✅ قسم الدفع المختلط (كاش + شبكة) --}}
                        <div x-show="sale_type === 'mixed'"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="mt-4 p-4 ui-status-info-bg border ui-border rounded-xl space-y-3">
                                <p class="ui-text-caption ui-status-info flex items-center gap-2">
                                    <i class="fa-solid fa-info-circle"></i>
                                    أدخل المبلغ المدفوع نقداً والمبلغ المدفوع بالشبكة
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="ui-text-muted ui-text-caption block mb-1">💵 نقداً</label>
                                        <input type="number" step="1" x-model.number="mixedCash" @focus="$event.target.select()" @input="updateMixedTotal" @wheel="preventWheelChange($event)" placeholder="مبلغ الكاش"
                                               class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-center font-black outline-none placeholder:ui-text-caption placeholder:font-bold">
                                    </div>
                                    <div>
                                        <label class="ui-text-muted ui-text-caption block mb-1">💳 شبكة</label>
                                        <input type="number" step="1" x-model.number="mixedCard" @focus="$event.target.select()" @input="updateMixedTotal" @wheel="preventWheelChange($event)" placeholder="مبلغ الشبكة"
                                               class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-center font-black outline-none placeholder:ui-text-caption placeholder:font-bold">
                                    </div>
                                </div>
                                <div class="ui-section-divider ui-section-divider-sm space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="ui-text-muted ui-text-caption font-bold block pr-1 italic text-right">💵 المبلغ المستلم</label>
                                        <button type="button" data-ui-help-title="المبلغ المستلم" data-ui-help-body="يُحتسب المبلغ المستلم تلقائياً من مجموع الكاش والشبكة." class="ui-help-btn"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                                    </div>
                                    <input type="number" step="1" :value="Math.round(mixedTotal)" readonly class="w-full ui-surface-muted-bg border ui-border ui-status-success rounded-xl px-4 py-3 text-2xl text-center font-black outline-none shadow-inner cursor-not-allowed">
                                    <div x-show="hasPartialCredit" x-transition class="space-y-1">
                                        <label class="ui-text-muted ui-text-caption font-bold block pr-1 italic text-right">🧮 إجمالي تغطية العملية</label>
                                        <input type="number" step="1" :value="Math.round(operationCoverage)" readonly :class="operationCoverage < final_total ? 'ui-border ui-status-danger' : 'ui-border ui-status-success'" class="w-full ui-surface-muted-bg rounded-xl border px-4 py-3 text-center font-black outline-none shadow-inner cursor-not-allowed">
                                    </div>
                                </div>
                        </div>

                        {{-- ✅ خيار الآجل الجزئي (يظهر مع كاش، شبكة، أو مختلط) --}}
                        <div x-show="sale_type === 'cash' || sale_type === 'card' || sale_type === 'mixed'"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="mt-4 p-4 ui-card">
                                <div class="flex items-center gap-2 mb-3">
                                    <input type="checkbox" x-model="hasPartialCredit" @change="if(!hasPartialCredit){partial_credit_amount=0;}" id="hasPartialCredit" class="w-4 h-4">
                                    <label for="hasPartialCredit" class="ui-title text-sm font-bold">تسجيل المبلغ المتبقي كآجل</label>
                                </div>

                                <div x-show="hasPartialCredit"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 -translate-y-1"
                                     class="space-y-3">
                                    <select x-model="employee_id" @wheel="preventWheelChange($event)" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-3 py-3 text-sm">
                                        <option value="">— اختر الموظف —</option>
                                        <template x-for="person in creditPersons" :key="person.id">
                                            <option :value="person.id" x-text="person.name"></option>
                                        </template>
                                    </select>

                                    {{-- يظهر حقل قيمة الأجل فقط بعد اختيار الآجل الجزئي والموظف المرتبط به. --}}
                                    <div x-show="employee_id" class="space-y-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="ui-status-warning ui-text-caption font-bold block pr-1 italic text-right">🧾 مبلغ الأجل المتبقي على الموظف</label>
                                            <button type="button" data-ui-help-title="مبلغ الأجل المتبقي" data-ui-help-body="أدخل الجزء المتبقي الذي سيُسجل كأجل على الموظف المختار." class="ui-help-btn"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                                        </div>
                                        <input type="number" step="1" min="0" x-model.number="partial_credit_amount" @focus="$event.target.select()" @wheel="preventWheelChange($event)" placeholder="أدخل المبلغ المتبقي كآجل" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-center font-black outline-none shadow-inner">
                                    </div>

                                    <div x-show="employee_id" class="space-y-1">
                                        <label class="ui-status-warning ui-text-caption font-bold block pr-1 italic text-right">📝 اسم العملية</label>
                                        <textarea x-model="credit_note" rows="2" placeholder="اسم العميل / السيارة / سبب الأجل" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-sm outline-none text-right font-bold"></textarea>
                                    </div>
                                </div>
                        </div>

                        {{-- خيار الآجل الكامل (يظهر فقط مع آجل) --}}
                        <div x-show="sale_type === 'credit'"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2"
                             class="mt-2 space-y-3">
                                <select x-model="employee_id" @wheel="preventWheelChange($event)" class="w-full ui-surface-muted-bg border-2 ui-border ui-title rounded-xl px-3 py-3 text-sm font-bold text-right outline-none">
                                    <option value="">— حدد الموظف —</option>
                                    <template x-for="person in creditPersons" :key="person.id">
                                        <option :value="person.id" x-text="person.name"></option>
                                    </template>
                                </select>

                                <div>
                                    <label class="ui-status-warning ui-text-caption font-bold block mb-1 pr-1 italic text-right">🧾 القيمة الآجلة</label>
                                    <input type="number" step="1" min="0" x-model="agreed_credit_total" @focus="$event.target.select()" @wheel="preventWheelChange($event)" placeholder="أدخل قيمة الأجل" class="w-full ui-surface-muted-bg border-2 ui-border ui-title rounded-xl px-4 py-3 text-lg text-center font-black outline-none shadow-inner placeholder:text-sm placeholder:font-bold sm:text-xl">
                                    <p class="ui-text-caption ui-status-warning mt-1 pr-1">يجب إدخال قيمة آجل لا تقل عن إجمالي الفاتورة الحالي: <span class="font-black" x-text="Math.round(base_final_total) + ' ر.س'"></span>.</p>
                                </div>

                                <div>
                                    <label class="ui-status-warning ui-text-caption font-bold block mb-1 pr-1 italic text-right">📝 اسم العملية</label>
                                    <textarea x-model="credit_note" rows="2" placeholder="اسم العميل / السيارة / سبب الأجل" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3 text-sm outline-none text-right font-bold"></textarea>
                                </div>
                        </div>
                    </div>
                </div>

                <div class="ui-surface-muted-bg rounded-xl p-4 mb-6 grid grid-cols-1 gap-3 border ui-border text-right text-sm sm:grid-cols-2">
                    <div class="flex justify-between ui-text-muted font-bold items-center">
                        <span>أجور اليد:</span>
                        <span x-text="Math.round(labor_total || 0) + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between ui-text-muted font-bold items-center">
                        <span>الضريبة:</span>
                        <span x-text="Math.round(tax_value) + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between ui-text-muted font-bold items-center">
                        <span>مجموع المنتجات:</span>
                        <span x-text="Math.round(items_total) + ' ر.س'"></span>
                    </div>
                    <div class="ui-frame-row ui-section-divider-sm ui-title font-bold text-xl">
                        <span class="ui-status-info font-black">الإجمالي النهائي:</span>
                        <span class="ui-status-info text-2xl font-black" x-text="Math.round(final_total) + ' ر.س'"></span>
                    </div>
                    <div class="ui-frame-row ui-section-divider-sm ui-status-warning font-bold">
                        <span x-text="sale_type === 'credit' ? 'إجمالي الأجل:' : 'المتبقي (أجل):'"></span>
                        <span class="font-black" x-text="Math.round(Math.max(0, remaining)) + ' ر.س'"></span>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- ✅ خيار إنشاء الفاتورة مع زر التوضيح المعتمد --}}
                    <div class="flex items-center gap-2 ui-surface-muted-bg p-3 rounded-xl border ui-border mt-4">
                        <input type="checkbox" x-model="has_invoice" id="has_invoice" class="w-5 h-5 rounded ui-border ui-surface-muted-bg ui-status-info ">
                        <label for="has_invoice" class="ui-title text-sm font-bold cursor-pointer">إصدار فاتورة ضريبية للمطبوعات</label>
                        <button type="button" data-ui-help-title="الفاتورة الضريبية" data-ui-help-body="عند تفعيل هذا الخيار سيتم إنشاء فاتورة ضريبية بعد حفظ عملية البيع." class="mr-auto inline-flex h-5 w-5 items-center justify-center rounded-full border ui-border ui-text-caption ui-text-muted cursor-help ui-surface-muted-bg"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button>
                    </div>
                </div>


                <div class="ui-card p-4 space-y-3">
                    <h3 class="ui-title font-black text-sm">ملخص العملية قبل التأكيد</h3>
                    <div class="grid grid-cols-2 gap-3 ui-text-caption">
                        <div class="ui-surface-muted-bg rounded-lg px-3 py-2 border ui-border">
                            <div class="ui-text-muted">نوع الدفع</div>
                            <div class="ui-title font-black mt-1" x-text="sale_type ? (sale_type === 'cash' ? 'كاش' : sale_type === 'card' ? 'شبكة' : sale_type === 'mixed' ? 'مختلط' : 'آجل') : 'غير محدد'"></div>
                        </div>
                        <div class="ui-surface-muted-bg rounded-lg px-3 py-2 border ui-border">
                            <div class="ui-text-muted">الإجمالي</div>
                            <div class="ui-status-info font-black mt-1" x-text="Math.round(final_total) + ' ر.س'"></div>
                        </div>
                        <div class="ui-surface-muted-bg rounded-lg px-3 py-2 border ui-border">
                            <div class="ui-text-muted">المدفوع</div>
                            <div class="ui-status-success font-black mt-1" x-text="sale_type === 'mixed' ? (Math.round(mixedTotal) + ' ر.س') : (sale_type === 'credit' ? '0 ر.س' : (Math.round(paid_amount || 0) + ' ر.س'))"></div>
                        </div>
                        <div class="ui-surface-muted-bg rounded-lg px-3 py-2 border ui-border">
                            <div class="ui-text-muted" x-text="sale_type === 'credit' ? 'الأجل' : 'المتبقي'"></div>
                            <div class="ui-status-warning font-black mt-1" x-text="Math.round(Math.max(0, remaining)) + ' ر.س'"></div>
                        </div>
                    </div>
                    <div x-show="employee_id" class="ui-text-caption ui-text-muted ui-section-divider ui-section-divider-sm">
                        الموظف المرتبط: <span class="font-black ui-title" x-text="creditPersons.find(person => String(person.id) === String(employee_id))?.name || 'غير معروف'"></span>
                    </div>
                </div>

                <div x-show="pendingWarningVisible && !pendingStatusChecking" x-cloak class="mt-4 rounded-2xl border ui-border ui-status-warning-bg p-4 text-sm ui-status-warning">
                    <div class="font-black mb-1">تنبيه عملية بيع غير مؤكدة</div>
                    <p class="leading-6">توجد عملية ضغطت اعتمادها سابقاً ولم تصل نتيجة واضحة للمتصفح. اضغط فحص الحالة لمعرفة هل سُجلت أو لا قبل إعادة البيع.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="checkPendingOperation()" class="ui-btn ui-btn-warning px-4 py-2">فحص الحالة</button>
                    </div>
                </div>

                <form method="POST" action="{{ route('accountant.quick-sale.submit') }}" x-ref="saleForm" class="mt-6">
                    @csrf
                    <input type="hidden" name="items" x-model="items_json">
                    <input type="hidden" name="labor_total" :value="Math.round(labor_total)">
                    <input type="hidden" name="paid_amount" :value="sale_type === 'credit' ? 0 : (sale_type === 'mixed' ? Math.round(mixedTotal) : Math.round(paid_amount))">
                    <input type="hidden" name="tax_rate" x-model="tax_rate">
                    <input type="hidden" name="sale_type" x-model="sale_type">
                    <input type="hidden" name="employee_id" x-model="employee_id">
                    <input type="hidden" name="description" x-model="description">
                    <input type="hidden" name="operation_name" x-model="credit_note">
                    <input type="hidden" name="has_invoice" :value="has_invoice ? 1 : 0">
                    <input type="hidden" name="has_partial_credit" :value="hasPartialCredit ? 1 : 0">
                    <input type="hidden" name="debt_amount" :value="hasPartialCredit ? Math.round(partial_credit_amount || 0) : ''">
                    <input type="hidden" name="mixed_cash" :value="mixedCash">
                    <input type="hidden" name="mixed_card" :value="mixedCard">
                    <input type="hidden" name="agreed_credit_total" :value="sale_type === 'credit' ? Math.round(agreed_credit_total || 0) : ''">
                    <input type="hidden" name="client_operation_id" :value="clientOperationId">
                    <input type="hidden" name="quick_sale_submit_token" value="{{ $quickSaleSubmitToken }}">

                    <button type="button" @click="prepareForm($refs.saleForm)" :disabled="saleSubmitting"
                            :class="saleSubmitting ? 'cursor-wait opacity-80' : (!sale_type ? 'is-disabled cursor-not-allowed opacity-80' : 'active:scale-95')"
                            class="ui-btn ui-btn-success ui-quick-sale-confirm w-full py-5 rounded-2xl font-black text-xl transition-all">
                        <span x-text="saleSubmitting ? 'جاري اعتماد العملية... لا تغلق الصفحة' : (sale_type ? 'تأكيد العملية ✅' : 'اختر نوع الدفع أولاً')"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@if($hasAvailableTintProducts)
@include('cashier.quick-sale.partials.tint-modal')
@endif

{{-- عقد إعداد البيع السريع: ينقل المسارات والحالات فقط، وتبقى الحسابات والتحقق في الوحدة المستخرجة دون تغيير. --}}
<div class="hidden" data-quick-sale-config="{{ json_encode([
    'laborDescriptionOptions' => $laborDescriptionOptions ?? ['تضليل', 'تجليد', 'شغل يد'],
    'hasStoreTaxNumber' => (bool) auth('accountant')->user()->store->tax_number,
    'hasApprovedTaxNumber' => (bool) ($hasApprovedTaxNumber ?? false),
    'clearPendingOnSuccess' => (bool) session('success'),
    'operationStatusBaseUrl' => url('/accountant/quick-sale/operation-status'),
    'pendingWarningDelayMs' => 60 * 1000,
    'productSearchUrl' => route('accountant.products.search'),
], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endsection
