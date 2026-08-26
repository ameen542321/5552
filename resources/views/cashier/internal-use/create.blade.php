@extends('dashboard.app')

@section('title', 'استهلاك المحاسب')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-5 sm:py-8 space-y-6 text-right"
     x-data="internalUseForm()"
     x-init="init()"
     x-cloak
     dir="rtl">

    {{-- الهيدر - متوافق مع الهوية الداكنة --}}
    <div class="rounded-2xl border ui-border ui-surface-muted-bg p-4 sm:p-5 shadow-xl">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl ui-card   flex items-center justify-center shadow-lg ">
                <i class="fa-solid fa-box-open ui-title text-lg sm:text-xl"></i>
            </div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-bold ui-title">استهلاك المحاسب</h1>
                <x-ui.help title="استهلاك المحاسب" body="يخصم النظام الكمية من المخزون ويسجلها كاستهلاك عملي ضمن يوم العمل الحالي." />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('accountant.dashboard') }}"
               class="ui-btn ui-btn-secondary px-3 py-2 sm:px-4 sm:py-2.5 ui-text-caption sm:text-sm group relative">
                <svg class="w-3 h-3 sm:w-4 sm:h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="hidden sm:inline"> الرئيسية </span>
                <span class="sm:hidden">رجوع</span>
            </a>
        </div>
    </div>
    </div>

    {{-- النموذج الرئيسي --}}
    <div class="ui-card sm:rounded-2xl p-4 sm:p-6 md:p-8 shadow-xl">
        <form action="{{ route('accountant.internal-use.store') }}" method="POST" class="space-y-4 sm:space-y-6">
            @csrf

            <div class="space-y-4 sm:space-y-6">
                {{-- اختيار المنتج مع البحث --}}
                <div class="space-y-2">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <label class="text-sm font-bold ui-text-muted flex items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass ui-status-info ui-text-caption"></i>
                            اختر المنتج المستهلك
                        </label>
                        <span class="ui-text-caption ui-text-muted">لا تظهر المنتجات المخفية أو منتجات قسم التضليل هنا.</span>
                    </div>

                    {{-- حقل البحث --}}
                    <div class="relative mb-2">
                        <input type="text"
                               x-model="searchQuery"
                               @input="filterProducts"
                               placeholder="🔍 ابحث باسم المنتج أو الوصف أو الباركود..."
                               class="ui-input px-4 py-3 pr-10 text-sm">
                        <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted"></i>
                        <button type="button"
                                x-show="searchQuery"
                                @click="clearSearch"
                                class="absolute right-4 top-1/2 -translate-y-1/2 ui-text-muted">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>

                    <input type="hidden" name="product_id" x-model="productId" required>

                    {{-- قائمة المنتجات --}}
                    <div class="border ui-border rounded-xl ui-surface-muted-bg max-h-80 overflow-y-auto">
                        <div class="divide-y divide-ui-border">
                            <template x-for="product in filteredProducts" :key="product.id">
                                <button type="button"
                                        @click="selectProduct(product.id)"
                                        class="w-full text-right px-3 py-3 transition"
                                        :class="productId == product.id
                                            ? 'ui-status-info-bg ui-title'
                                            : 'ui-surface-muted-bg ui-text-muted ui-surface-muted-bg'">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-bold text-sm truncate" x-text="product.name"></p>
                                        <span class="ui-text-caption ui-text-muted" x-text="product.barcode || 'بدون باركود'"></span>
                                    </div>
                                    <p class="ui-text-caption mt-1 ui-text-muted truncate" x-show="product.description" x-text="product.description"></p>
                                    <p class="ui-text-caption mt-1 ui-text-muted" x-text="product.stock_label"></p>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- عدد النتائج --}}
                    <div x-show="searchQuery" class="ui-text-caption ui-text-muted" x-text="filteredProducts.length + ' نتيجة'"></div>

                    {{-- رسالة عدم وجود نتائج --}}
                    <div x-show="searchQuery && filteredProducts.length === 0"
                         class="mt-2 p-3 ui-status-warning-bg border ui-border ui-status-warning rounded-lg text-sm">
                        <i class="fa-solid fa-exclamation-triangle ml-2"></i>
                        لا توجد منتجات مطابقة لبحث " <span x-text="searchQuery"></span> "
                    </div>
                </div>

                {{-- معلومات المنتج المحدد --}}
                <template x-if="productId && selectedProduct">
                    <div class="ui-surface-muted-bg border ui-border rounded-lg sm:rounded-xl p-3 sm:p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold ui-title text-lg sm:text-xl truncate"
                                    x-text="selectedProduct.name">
                                </h3>

                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-4 mt-2 sm:mt-0">
                                <div class="text-left sm:text-right">
                                    {{-- عرض المخزون بشكل مفصل --}}
                                    <div class="text-base sm:text-lg font-bold ui-status-success"
                                         x-html="getDetailedStockDisplay()">
                                    </div>

                                    {{-- عرض طول الرول للمنتجات الكسرية --}}
                                    <template x-if="selectedProduct.productType === 'fractional' && selectedProduct.rollLength > 0">
                                        <div class="ui-text-caption ui-text-muted mt-1">
                                            <i class="fa-solid fa-ruler"></i>
                                            طول الرول: <span class="ui-title" x-text="selectedProduct.rollLength + ' م'"></span>
                                        </div>
                                    </template>

                                    {{-- عرض عدد الحبات في الطقم للمنتجات القابلة للتجزئة --}}
                                    <template x-if="selectedProduct.isSplittable && selectedProduct.itemsPerUnit > 1">
                                        <div class="ui-text-caption ui-text-muted mt-1">
                                            <i class="fa-solid fa-cubes"></i>
                                            الطقم: <span class="ui-title" x-text="selectedProduct.itemsPerUnit + ' حبة'"></span>
                                        </div>
                                    </template>

                                    {{-- عرض نسبة الهالك إن وجدت --}}
                                    <template x-if="selectedProduct.wastePercentage > 0">
                                        <div class="ui-text-caption ui-status-warning mt-1">
                                            <i class="fa-solid fa-flask"></i>
                                            هالك: <span x-text="selectedProduct.wastePercentage + '%'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- اختيار وحدة القياس للمنتجات القابلة للتجزئة --}}
                <template x-if="productId && selectedProduct && selectedProduct.isSplittable">
                    <div class="space-y-2">
                        <label class="text-sm font-medium ui-text-muted">وحدة الخصم</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-3 ui-surface-muted-bg border ui-border rounded-lg cursor-pointer transition"
                                   :class="{'ui-border': unitType === 'kit'}">
                                <input type="radio" x-model="unitType" value="kit" class="sr-only">
                                <i class="fa-solid fa-cubes ui-status-info"></i>
                                <span class="text-sm ui-title">طقم كامل</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 ui-surface-muted-bg border ui-border rounded-lg cursor-pointer transition"
                                   :class="{'ui-border': unitType === 'piece'}">
                                <input type="radio" x-model="unitType" value="piece" class="sr-only">
                                <i class="fa-solid fa-cube ui-status-info"></i>
                                <span class="text-sm ui-title">حبة</span>
                            </label>
                        </div>
                    </div>
                </template>

                {{-- اختيار وحدة القياس للمنتجات الكسرية --}}
                <template x-if="productId && selectedProduct && selectedProduct.productType === 'fractional'">
                    <div class="space-y-2">
                        <label class="text-sm font-medium ui-text-muted">وحدة القياس</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-3 ui-surface-muted-bg border ui-border rounded-lg cursor-pointer transition"
                                   :class="{'ui-border': unitType === 'roll'}">
                                <input type="radio" x-model="unitType" value="roll" class="sr-only">
                                <i class="fa-solid fa-roll-forward ui-status-info"></i>
                                <span class="text-sm ui-title">رول</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 ui-surface-muted-bg border ui-border rounded-lg cursor-pointer transition"
                                   :class="{'ui-border': unitType === 'meters'}">
                                <input type="radio" x-model="unitType" value="meters" class="sr-only">
                                <i class="fa-solid fa-ruler ui-status-info"></i>
                                <span class="text-sm ui-title">أمتار</span>
                            </label>
                        </div>
                    </div>
                </template>

                {{-- الكمية - تظهر فقط بعد اختيار نوع الوحدة --}}
                <template x-if="productId && selectedProduct && unitType !== 'default'">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium ui-text-muted">
                                <span x-show="unitType === 'meters'">الأمتار المطلوبة</span>
                                <span x-show="unitType === 'piece'">عدد الحبات</span>
                                <span x-show="unitType === 'kit'">عدد الأطقم</span>
                                <span x-show="unitType === 'roll'">عدد الرولات</span>
                            </label>
                            <span class="ui-text-caption ui-text-muted">
                                المتوفر: <span class="font-bold" x-html="getAvailableStockDisplay()"></span>
                            </span>
                        </div>

                        {{-- أزرار الأمتار --}}
                        <div x-show="unitType === 'meters'" class="space-y-3">
                            {{-- تنبيه توضيحي للأمتار --}}
                            <div class="ui-status-info-bg border ui-border ui-status-info rounded-lg p-3 ui-text-caption flex items-start gap-2">
                                <i class="fa-solid fa-info-circle mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <span class="font-bold">تنبيه:</span> سيتم خصم الأمتار المدخلة مباشرة من إجمالي المخزون.
                                    <span class="block mt-1 ui-status-info">مثال: ربع متر = 0.25 متر من إجمالي <span x-text="selectedProduct.stock.toFixed(2) + ' متر'"></span></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2">
                                <button type="button"
                                        @click="setMeterQuantity(0.25)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    ¼ متر
                                    <span class="ui-tooltip-popover pointer-events-none">
                                        ربع متر (0.25 م)
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setMeterQuantity(0.5)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    ½ متر
                                    <span class="ui-tooltip-popover pointer-events-none">
                                        نصف متر (0.5 م)
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setMeterQuantity(1)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    1 متر
                                    <span class="ui-tooltip-popover pointer-events-none">
                                        متر واحد
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setMeterQuantity(getMaxValue())"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    الكل
                                    <span class="ui-tooltip-popover pointer-events-none"
                                          x-text="'كل المتوفر (' + getMaxValue().toFixed(2) + ' م)'">
                                    </span>
                                </button>
                            </div>

                            {{-- حقل إدخال الأمتار --}}
                            <div class="relative mt-3">
                                <input type="number"
                                       name="quantity"
                                       x-model="quantity"
                                       step="0.01"
                                       :min="0.01"
                                       :max="getMaxValue()"
                                       required
                                       @input="validateMeterQuantity"
                                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg sm:rounded-xl px-4 py-4 text-center   outline-none transition text-base"
                                       placeholder="أدخل الأمتار المطلوبة">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted text-base">م</div>
                            </div>
                        </div>

                        {{-- أزرار الرولات --}}
                        <div x-show="unitType === 'roll'" class="space-y-3">
                            {{-- تنبيه توضيحي للرولات --}}
                            <div class="ui-status-warning-bg border ui-border ui-status-warning rounded-lg p-3 ui-text-caption flex items-start gap-2">
                                <i class="fa-solid fa-triangle-exclamation mt-0.5 flex-shrink-0"></i>
                                <div>
                                    <span class="font-bold">تنبيه:</span> عند اختيار رول، سيتم خصم <span x-text="selectedProduct.rollLength + ' متر'"></span> لكل رول كامل.
                                    <span class="block mt-1 ui-status-warning">مثال: نصف رول = <span x-text="(selectedProduct.rollLength / 2).toFixed(2) + ' متر'"></span></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2">
                                <button type="button"
                                        @click="setRollQuantity(0.25)"
                                        class="ui-surface-muted-bg ui-status-warning-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    ¼ رول
                                    <span class="ui-tooltip-popover pointer-events-none"
                                          x-text="'ربع رول (ما يعادل ' + (selectedProduct.rollLength * 0.25).toFixed(2) + ' م)'">
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setRollQuantity(0.5)"
                                        class="ui-surface-muted-bg ui-status-warning-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    ½ رول
                                    <span class="ui-tooltip-popover pointer-events-none"
                                          x-text="'نصف رول (ما يعادل ' + (selectedProduct.rollLength * 0.5).toFixed(2) + ' م)'">
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setRollQuantity(1)"
                                        class="ui-surface-muted-bg ui-status-warning-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    1 رول
                                    <span class="ui-tooltip-popover pointer-events-none"
                                          x-text="'رول كامل (' + selectedProduct.rollLength + ' م)'">
                                    </span>
                                </button>
                                <button type="button"
                                        @click="setRollQuantity(getMaxValue())"
                                        class="ui-surface-muted-bg ui-status-warning-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition relative group border ui-border">
                                    الكل
                                    <span class="ui-tooltip-popover pointer-events-none"
                                          x-text="'كل الرولات (' + getMaxValue().toFixed(2) + ' رول)'">
                                    </span>
                                </button>
                            </div>

                            {{-- حقل إدخال الرولات --}}
                            <div class="relative mt-3">
                                <input type="number"
                                       name="quantity"
                                       x-model="quantity"
                                       step="0.001"
                                       :min="0.001"
                                       :max="getMaxValue()"
                                       required
                                       @input="validateRollQuantity"
                                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg sm:rounded-xl px-4 py-4 text-center   outline-none transition text-base"
                                       placeholder="أدخل عدد الرولات">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted text-base">رول</div>
                            </div>
                        </div>

                        {{-- للأطقم --}}
                        <div x-show="unitType === 'kit'" class="space-y-3">
                            <div class="ui-status-success-bg border ui-border ui-status-success rounded-lg p-3 ui-text-caption">
                                <i class="fa-solid fa-cubes"></i>
                                سيتم خصم الأطقم كاملة من المخزون
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <button type="button"
                                        @click="setKitQuantity(1)"
                                        class="ui-surface-muted-bg ui-status-success-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    1 طقم
                                </button>
                                <button type="button"
                                        @click="setKitQuantity(2)"
                                        class="ui-surface-muted-bg ui-status-success-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    2 طقم
                                </button>
                                <button type="button"
                                        @click="setKitQuantity(3)"
                                        class="ui-surface-muted-bg ui-status-success-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    3 طقم
                                </button>
                                <button type="button"
                                        @click="setKitQuantity(getMaxValue())"
                                        class="ui-surface-muted-bg ui-status-success-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    الكل
                                </button>
                            </div>

                            {{-- حقل إدخال الأطقم --}}
                            <div class="relative mt-3">
                                <input type="number"
                                       name="quantity"
                                       x-model="quantity"
                                       step="1"
                                       :min="1"
                                       :max="getMaxValue()"
                                       required
                                       @input="validateKitQuantity"
                                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg sm:rounded-xl px-4 py-4 text-center   outline-none transition text-base"
                                       placeholder="أدخل عدد الأطقم">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted text-base">طقم</div>
                            </div>
                        </div>

                        {{-- للحبات --}}
                        <div x-show="unitType === 'piece'" class="space-y-3">
                            <div class="ui-status-info-bg border ui-border ui-status-info rounded-lg p-3 ui-text-caption">
                                <i class="fa-solid fa-cube"></i>
                                سيتم خصم الحبات بشكل فردي (تحسب كسور من الطقم)
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <button type="button"
                                        @click="setPieceQuantity(1)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    1 حبة
                                </button>
                                <button type="button"
                                        @click="setPieceQuantity(2)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    2 حبة
                                </button>
                                <button type="button"
                                        @click="setPieceQuantity(3)"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    3 حبة
                                </button>
                                <button type="button"
                                        @click="setPieceQuantity(getMaxValue())"
                                        class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                    الكل
                                </button>
                            </div>

                            {{-- حقل إدخال الحبات --}}
                            <div class="relative mt-3">
                                <input type="number"
                                       name="quantity"
                                       x-model="quantity"
                                       step="1"
                                       :min="1"
                                       :max="getMaxValue()"
                                       required
                                       @input="validatePieceQuantity"
                                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg sm:rounded-xl px-4 py-4 text-center   outline-none transition text-base"
                                       placeholder="أدخل عدد الحبات">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted text-base">حبة</div>
                            </div>
                        </div>

                        {{-- عرض تفاصيل الخصم --}}
                        <div x-show="quantity > 0" class="mt-2 text-sm ui-surface-muted-bg rounded-lg p-3">
                            <div class="flex justify-between ui-text-muted">
                                <span class="font-medium">سيتم الخصم:</span>
                                <span class="font-bold ui-status-info" x-html="getDeductionDetails()"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- للمنتجات العادية (قطعة) --}}
                <template x-if="productId && selectedProduct && !selectedProduct.isSplittable && selectedProduct.productType !== 'fractional'">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium ui-text-muted">الكمية (قطعة)</label>
                            <span class="ui-text-caption ui-text-muted">
                                المتوفر: <span class="font-bold ui-title" x-text="Math.floor(selectedProduct.stock) + ' قطعة'"></span>
                            </span>
                        </div>

                        {{-- أزرار سريعة --}}
                        <div class="grid grid-cols-4 gap-2">
                            <button type="button"
                                    @click="setNormalQuantity(1)"
                                    class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                1
                            </button>
                            <button type="button"
                                    @click="setNormalQuantity(2)"
                                    class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                2
                            </button>
                            <button type="button"
                                    @click="setNormalQuantity(3)"
                                    class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                3
                            </button>
                            <button type="button"
                                    @click="setNormalQuantity(Math.floor(selectedProduct.stock))"
                                    class="ui-surface-muted-bg ui-status-info-bg ui-text-muted ui-hover-info py-3 rounded-lg ui-text-caption sm:text-sm transition border ui-border">
                                الكل
                            </button>
                        </div>

                        {{-- حقل إدخال الكمية --}}
                        <div class="relative mt-3">
                            <input type="number"
                                   name="quantity"
                                   x-model="quantity"
                                   step="1"
                                   :min="1"
                                   :max="Math.floor(selectedProduct.stock)"
                                   required
                                   @input="validateNormalQuantity"
                                   class="w-full ui-surface-muted-bg border ui-border ui-title rounded-lg sm:rounded-xl px-4 py-4 text-center   outline-none transition text-base"
                                   placeholder="أدخل عدد القطع">
                            <div class="absolute left-4 top-1/2 -translate-y-1/2 ui-text-muted text-base">قطعة</div>
                        </div>

                        {{-- تحذير الكمية الزائدة --}}
                        <template x-if="quantity > Math.floor(selectedProduct.stock)">
                            <div class="p-2 ui-status-danger-bg border ui-border ui-status-danger rounded-lg ui-text-caption">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                الكمية المتوفرة: <span x-text="Math.floor(selectedProduct.stock) + ' قطعة'"></span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ملاحظات داخلية فقط --}}
                <div class="space-y-2">
                    <label class="text-sm font-medium ui-text-muted">ملاحظات (اختياري)</label>
                    <textarea name="internal_notes"
                              x-model="internalNotes"
                              placeholder="أدخل ملاحظات عن استهلاك المحاسب..."
                              rows="2"
                              class="ui-input px-3 py-3 resize-none text-sm">
                    </textarea>
                </div>

                {{-- حقل خفي لنوع الوحدة والكمية --}}
                <input type="hidden" name="unit_type" x-model="unitType">
                <input type="hidden" name="quantity" x-model="quantity">

                {{-- زر الإرسال --}}
                <button type="submit"
                        :disabled="!canSubmit()"
                        class="ui-btn ui-btn-primary w-full py-4 text-base disabled:cursor-not-allowed disabled:opacity-60">
                    <i class="fa-solid fa-check-circle text-lg"></i>
                    <span>تأكيد خصم المخزون</span>
                </button>

                {{-- رسائل التحذير --}}
                <template x-if="productId && selectedProduct && selectedProduct.stock <= 0">
                    <div class="p-3 ui-status-warning-bg border ui-border ui-status-warning rounded-xl flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <span>المنتج غير متوفر في المخزون</span>
                    </div>
                </template>
            </div>
        </form>
    </div>
</div>




{{-- عقد بيانات منتجات الاستهلاك؛ لا يغير الكميات أو حقول النموذج. --}}
<div class="hidden" data-internal-use-config="{{ json_encode(['products' => $products], JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-hidden="true"></div>
@endsection
