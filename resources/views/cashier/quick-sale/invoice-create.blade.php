@extends('dashboard.app')

@section('title', 'إصدار فاتورة ضريبية')

@section('content')
{{-- x-data المالي: يحسب الضريبة على المنتجات فقط ويجمعها مع أجور اليد --}}
<div class="max-w-4xl mx-auto py-8 px-4" x-data="{
    productsNet: {{ $sale->products_total ?? 0 }},
    laborNet: {{ $sale->labor_total ?? 0 }},
    taxRate: {{ $sale->tax_rate ?? 0 }},

    // 1. الصافي: مجموع المنتج وشغل اليد بدون ضريبة
    get subtotal() {
        return (parseFloat(this.productsNet) + parseFloat(this.laborNet)).toFixed(2);
    },

    // 2. قيمة الضريبة: تُحسب على المنتجات فقط
    get taxAmount() {
        return (parseFloat(this.productsNet) * (this.taxRate / 100)).toFixed(2);
    },

    // 3. الإجمالي النهائي: الصافي + ضريبة المنتجات
    get finalTotal() {
        return (parseFloat(this.subtotal) + parseFloat(this.taxAmount)).toFixed(2);
    }
}">

    {{-- رأس الصفحة --}}
    <div class="flex items-center justify-between mb-8 text-right" dir="rtl">
        <div>
            <h1 class="text-3xl font-extrabold ui-title">إصدار فاتورة ضريبية</h1>
            <p class="ui-text-muted mt-1 italic">ربط مع عملية البيع: #{{ $sale->id }}</p>
        </div>
        <div class="text-left">
            <span class="ui-text-muted block text-sm font-bold">المبلغ المستحق</span>
            <span class="text-3xl font-black ui-status-success" x-text="finalTotal + ' ر.س'"></span>
        </div>
    </div>

    <form method="POST" action="{{ route('accountant.quick-sale.invoice.store', $sale->id) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8 text-right" dir="rtl">
        @csrf

        <div class="lg:col-span-2 space-y-6">

            {{-- 1. تفاصيل المنتجات والمواد --}}
            <div class="ui-card p-6 shadow-xl text-right">
                <h2 class="text-lg font-semibold ui-title mb-4 flex items-center gap-2 border-b ui-border pb-3">
                    <span class="ui-status-warning">📦</span> المنتجات والمواد (خاضعة للضريبة)
                </h2>

                <div class="space-y-3">
                    @forelse($sale->items as $item)
                        <div class="flex justify-between items-center ui-surface-muted-bg p-4 rounded-xl border ui-border transition-hover">
                            <div class="flex-1">
                                <span class="ui-title font-bold block">
                                    {{ $item->historical_product_name }}
                                    {{-- إظهار اسم العمل المخصص --}}
                                    @if($item->is_custom && $item->custom_name)
                                        <span class="ui-status-info ui-text-caption font-normal"> - {{ $item->custom_name }}</span>
                                    @endif
                                </span>

                                <span class="ui-text-muted ui-text-caption italic">
                                    @if($item->is_custom && $item->custom_meters)
                                        {{-- عرض الكمية بالأمتار --}}
                                        الكمية: <span class="ui-text-muted font-bold font-sans">{{ number_format($item->custom_meters, 2) }} متر</span>
                                        × {{ number_format($item->price, 2) }} ر.س
                                    @else
                                        الكمية: {{ $item->quantity }}
                                        @if($item->product && $item->product->product_type === 'fractional')
                                            (رول كامل)
                                        @endif
                                        × {{ number_format($item->price, 2) }} ر.س
                                    @endif
                                </span>
                            </div>
                            <div class="ui-status-info font-mono font-bold text-left min-w-[100px]">
                                {{ number_format($item->total, 2) }} <small class="ui-text-caption">ر.س</small>
                            </div>
                        </div>
                    @empty
                        <p class="ui-text-muted text-sm italic text-center py-4">لا توجد منتجات مسجلة.</p>
                    @endforelse
                </div>
            </div>

            {{-- 2. تفاصيل أجور اليد --}}
            <div class="ui-card p-6 shadow-xl text-right">
                <h2 class="text-lg font-semibold ui-title mb-4 flex items-center gap-2 border-b ui-border pb-3">
                    <span class="ui-status-info">🛠️</span> أجور اليد والتركيب (غير خاضعة)
                </h2>

                @if($sale->labor_total > 0)
                <div class="p-4 ui-status-info-bg border ui-border rounded-xl">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="ui-status-info font-bold block">وصف العمل</span>
                            <span class="ui-text-muted ui-text-caption italic">{{ $sale->description ?? 'تركيب وفحص عام' }}</span>
                        </div>
                        <span class="ui-status-info font-mono font-bold text-lg">
                            {{ number_format($sale->labor_total, 2) }} <small class="ui-text-caption">ر.س</small>
                        </span>
                    </div>
                </div>
                @else
                <p class="ui-text-muted text-sm italic text-center">لا توجد أجور يد مسجلة.</p>
                @endif
            </div>

            {{-- 3. بيانات العميل والمركبة --}}
            <div class="ui-card p-6 shadow-xl text-right">
                <h2 class="text-lg font-semibold ui-title mb-6 flex items-center gap-2 border-b ui-border pb-3">
                    <span class="ui-status-info">👤</span> بيانات العميل والمركبة
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="text-sm ui-text-muted block italic mr-1">اسم العميل</label>
                        <input type="text" name="customer_name" required placeholder="أدخل اسم العميل"
                               class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm ui-text-muted block italic mr-1">رقم الهاتف</label>
                        <input type="text" name="customer_phone" required placeholder="05xxxxxxxx"
                               class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 text-left outline-none font-mono">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm ui-status-warning block italic mr-1 font-bold">الرقم الضريبي للعميل (اختياري)</label>
                        <input type="text" name="customer_tax_number" placeholder="3xxxxxxxxxxxxxx"
                               class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 text-left outline-none font-mono"
                               maxlength="15">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm ui-text-muted block italic mr-1">نوع المركبة</label>
                        <input type="text" name="vehicle_type" required placeholder="مثلاً: لاندكروزر 2024"
                               class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm ui-text-muted block italic mr-1">رقم اللوحة</label>
                        <input type="text" name="plate_number" required placeholder="أ ب ج 1234"
                               class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 text-center font-bold outline-none font-sans">
                    </div>

                    <div class="space-y-2 md:col-span-2 ui-section-divider">
                        <label class="text-sm ui-status-warning block italic font-bold mr-1">📝 ملاحظات إضافية (تظهر في الفاتورة)</label>
                        <textarea name="notes" rows="2" placeholder="اكتب أي ملاحظات أخرى هنا..."
                                  class="w-full ui-surface-muted-bg border ui-border  ui-title rounded-xl px-4 py-3 outline-none"></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- الجانب الأيسر: الملخص المالي --}}
        <div class="lg:col-span-1">
            <div class="ui-card p-6 shadow-xl sticky top-8 text-right">
                <h2 class="text-lg font-semibold ui-title mb-6 border-b ui-border pb-3">الملخص المالي</h2>

                <div class="mb-6 p-4 ui-status-info-bg rounded-xl border ui-border">
                    <label class="ui-text-caption ui-status-info mb-1 block italic text-center font-bold">نسبة الضريبة</label>
                    <div class="text-3xl font-black ui-title text-center" x-text="taxRate + '%'"></div>
                    <input type="hidden" name="tax_rate" :value="taxRate">
                </div>

                <div class="space-y-4 text-sm">
                    <div class="flex justify-between ui-text-muted border-b ui-border pb-2">
                        <span>إجمالي الصافي:</span>
                        <span class="ui-title font-bold font-mono" x-text="subtotal + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between ui-text-muted border-b ui-border pb-2">
                        <div class="flex flex-col">
                            <span>قيمة الضريبة:</span>
                            <small class="ui-text-caption ui-text-muted italic">(على المنتجات فقط)</small>
                        </div>
                        <span class="ui-status-warning font-bold font-mono" x-text="taxAmount + ' ر.س'"></span>
                    </div>
                    <div class="flex justify-between text-xl font-black pt-4 ui-status-success-bg p-3 rounded-xl shadow-inner">
                        <span class="ui-title">الإجمالي:</span>
                        <span class="ui-status-success font-mono" x-text="finalTotal + ' ر.س'"></span>
                    </div>
                </div>

                <button type="submit"
                        class="w-full mt-8 ui-status-success-bg ui-title py-5 rounded-2xl font-black text-lg shadow-2xl transition-all flex items-center justify-center gap-3 active:scale-95">
                    <span>🖨️</span>
                    اعتماد وطباعة الفاتورة
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
