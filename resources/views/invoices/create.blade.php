@extends('dashboard.app')

@section('title', 'إصدار فاتورة جديدة')

@section('content')
@php
    $storeTaxNumber = !empty($isOwnerContext)
        ? (optional($store)->tax_number ?? '')
        : (optional(optional(auth('accountant')->user())->store)->tax_number ?? '');
@endphp
<div class="max-w-4xl mx-auto px-4 py-8 text-right" dir="rtl">

    {{-- رأس الصفحة --}}
    <div class="flex flex-col gap-6 mb-8">
        {{-- السطر العلوي للأزرار في الجوال والعنوان في الكبيرة --}}
        <div class="flex items-center justify-between w-full">
            <div class="hidden md:block">
                <h1 class="text-2xl font-black ui-title tracking-tight">إصدار فاتورة جديدة</h1>
                <p class="ui-text-muted text-sm mt-1">إدخال يدوي مباشر للبيانات والمبالغ</p>
            </div>

            <div class="flex-shrink-0 ml-auto md:ml-0">
                 <a href="{{ !empty($isOwnerContext) ? route('user.stores.invoices.index', $store->id) : route('accountant.invoices.index') }}"
                    class="ui-btn ui-btn-danger px-4 py-2.5 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    الرجوع للفواتير
                </a>
            </div>
        </div>

        {{-- العنوان في الجوال --}}
        <div class="md:hidden">
            <h1 class="text-xl font-black ui-title tracking-tight">إصدار فاتورة جديدة</h1>
            <p class="ui-text-muted ui-text-caption mt-1">إدخال يدوي مباشر للبيانات والمبالغ</p>
        </div>
    </div>

    {{-- تنبيهات الأخطاء --}}
    @if ($errors->any())
        <div class="mb-6 p-4 ui-status-danger-bg border ui-border rounded-xl ui-status-danger text-sm">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ !empty($isOwnerContext) ? route('user.stores.invoices.store', $store->id) : route('accountant.invoices.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 gap-6">

            {{-- القسم الأيمن: بيانات العميل والمركبة --}}
            <div class="space-y-6">
                <div class="ui-card p-6">
                    <h3 class="ui-status-info text-sm font-bold mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        معلومات العميل والمركبة
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm">اسم العميل</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                                class="w-full ui-card px-4 py-2.5 ui-title    outline-none transition-all"
                                placeholder="مثلاً: عميل نقدي">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm">رقم الجوال</label>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone') }}"
                                class="w-full ui-card px-4 py-2.5 ui-title  outline-none text-left"
                                placeholder="05xxxxxxxx">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm">نوع المركبة</label>
                            <input type="text" name="vehicle_type" value="{{ old('vehicle_type') }}"
                                class="w-full ui-card px-4 py-2.5 ui-title  outline-none"
                                placeholder="مثلاً: تويوتا كامري">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-bold">رقم اللوحة</label>

                            {{-- تصميم أبسط وأوضح للوحة السعودية --}}
                            <div class="w-full max-w-[320px] rounded-xl border-2 ui-border ui-surface-bg overflow-hidden mx-auto md:mx-0">
                                <div class="grid grid-cols-12 items-center">
                                    <div class="col-span-2 h-full ui-status-info-bg ui-title flex flex-col items-center justify-center py-2">
                                        <span class="ui-text-caption font-bold leading-none">KSA</span>
                                        <span class="ui-text-caption font-bold leading-none mt-1">السعودية</span>
                                    </div>
                                    <div class="col-span-10 px-3 py-2">
                                        <input type="text" name="plate_number" id="plate_number"
                                            class="w-full bg-transparent ui-text-muted text-[26px] md:text-[28px] font-black text-center tracking-[0.2em] outline-none placeholder-gray-400"
                                            placeholder="أ ب ج ١ ٢ ٣"
                                            dir="rtl"
                                            maxlength="12"
                                            autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <p class="ui-text-caption ui-text-muted mt-1 italic">مثال: أ ب ج 1 2 3</p>
                        </div>
                        <div class="sm:col-span-2 space-y-2">
                            <label class="ui-text-muted text-sm">الرقم الضريبي للعميل (اختياري)</label>
                            <input type="text" name="tax_number" value="{{ old('tax_number') }}"
                                class="w-full ui-card px-4 py-2.5 ui-title  outline-none">
                        </div>
                    </div>
                </div>
<div class="ui-card p-6">
    <div class="flex items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <label class="ui-title text-sm font-bold">المنتجات</label>
            <x-ui.help title="إدخال المنتجات" body="أدخل الكمية والسعر لكل صف، وسيتم احتساب إجمالي الصف والمبلغ قبل الضريبة تلقائيًا." />
        </div>
        <button type="button" id="add-service-line"
                class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
            + إضافة صف
        </button>
    </div>
    <div id="service-lines-list" class="space-y-2">
        @php($serviceLines = old('service_lines', ['']))
        @php($serviceValues = old('service_values', ['']))
        @php($serviceQtys = old('service_qtys', ['1']))
        @foreach($serviceLines as $line)
            <div class="flex items-center gap-2 service-line-row">
                <div class="flex-1 min-w-[140px]">
                    <input type="text" name="service_lines[]" value="{{ $line }}"
                           class="w-full ui-surface-muted-bg border ui-border rounded-lg px-3 py-2 ui-title text-sm"
                           placeholder="مثال: تضليل أمامي / تغيير زيت / تنظيف داخلي">
                </div>
                <div class="w-20">
                    <input type="number" step="1" min="0" name="service_qtys[]" value="{{ $serviceQtys[$loop->index] ?? 1 }}"
                           class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-qty-input"
                           placeholder="الكمية">
                </div>
                <div class="w-24">
                    <input type="number" step="0.01" min="0" name="service_values[]" value="{{ $serviceValues[$loop->index] ?? '' }}"
                           class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-title text-sm text-center service-value-input"
                           placeholder="السعر">
                </div>
                <div class="w-24">
                    <input type="number" step="0.01" min="0" name="service_totals[]" value="{{ (float) ($serviceQtys[$loop->index] ?? 1) * (float) ($serviceValues[$loop->index] ?? 0) }}"
                           class="w-full ui-surface-muted-bg border ui-border rounded-lg px-2 py-2 ui-status-success text-sm text-center service-total-input"
                           placeholder="الإجمالي" readonly>
                </div>
                <div class="w-10">
                    <button type="button"
                            class="remove-service-line ui-btn ui-btn-danger w-full h-full ui-text-caption" aria-label="حذف السطر">
                        ✕
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
                <div class="ui-card p-6">
                    <label class="ui-text-muted text-sm block mb-2 font-bold"> ملاحظات</label>
                    <textarea name="notes" rows="4"
                        class="w-full ui-card px-4 py-2.5 ui-title  outline-none resize-none"
                        placeholder=" ضمان ...">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- القسم الأيسر: الحسابات المالية --}}
            <div class="space-y-6">
                {{-- تنبيه إخلاء المسؤولية --}}
                <div id="tax_warning" class="hidden p-4 ui-status-warning-bg border ui-border rounded-2xl">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 ui-status-warning flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <p class="ui-text-caption ui-status-warning"><strong>تنبيه:</strong> تم اختيار ضريبة والمتجر لا يملك رقماً ضريبياً مسجلاً.</p>
                    </div>
                </div>

                <div class="ui-card p-6">
                    <h3 class="ui-text-muted ui-text-caption font-bold uppercase mb-6">الحساب المالي</h3>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="ui-text-muted ui-text-caption font-bold">المبلغ (قبل الضريبة)</label>
                            <input type="number" step="0.01" name="subtotal" id="subtotal" value="{{ old('subtotal') }}" required
                                class="w-full ui-card px-4 py-2.5 ui-title font-mono text-xl  outline-none"
                                placeholder="0.00">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted ui-text-caption font-bold">نسبة الضريبة (%)</label>
                            <div class="relative">
                                <select name="tax_rate" id="tax_rate"
                                    class="w-full ui-card px-4 py-2.5 ui-title font-mono  outline-none appearance-none cursor-pointer">
                                    <option value="0" {{ old('tax_rate', 0) == 0 ? 'selected' : '' }}>0% (بدون)</option>
                                    <option value="15" {{ old('tax_rate') == 15 ? 'selected' : '' }}>15% (القياسية)</option>
                                </select>
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none ui-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted ui-text-caption font-bold">طريقة الدفع</label>
                            <div class="relative">
                                <select name="sale_type" id="sale_type" required
                                    class="w-full ui-card px-4 py-2.5 ui-title  outline-none appearance-none cursor-pointer">
                                    <option value="" disabled {{ old('sale_type') ? '' : 'selected' }}>اختر طريقة الدفع</option>
                                    @foreach(\App\Support\PaymentTypeLabel::invoiceOptions() as $paymentTypeValue => $paymentTypeLabel)
                                        <option value="{{ $paymentTypeValue }}" {{ old('sale_type') === $paymentTypeValue ? 'selected' : '' }}>{{ $paymentTypeLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none ui-text-muted">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                        </div>

                        <div class="ui-section-divider ui-section-divider-lg space-y-4">
                            <div class="flex justify-between items-center text-sm">
                                <span class="ui-text-muted">مبلغ الضريبة:</span>
                                <span id="tax_amount_display" class="ui-title font-mono font-bold">0.00 ر.س</span>
                            </div>
                            <div class="flex justify-between items-center p-3 ui-status-success-bg rounded-xl border ui-border">
                                <span class="ui-status-success font-bold">الإجمالي:</span>
                                <span id="total_amount_display" class="text-2xl font-black ui-status-success font-mono">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="ui-btn ui-btn-primary w-full py-4 group">
                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    اعتماد وإصدار الفاتورة
                </button>
            </div>
        </div>
    </form>
</div>







{{-- عقد إعداد إنشاء الفاتورة؛ الرقم الضريبي للعرض والتحذير فقط ولا يغير بيانات الحفظ. --}}
<div class="hidden"
     data-invoice-create-config="{{ json_encode(['storeTaxNumber' => $storeTaxNumber ?? ''], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     aria-hidden="true"></div>
@endsection
