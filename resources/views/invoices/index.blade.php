@extends('dashboard.app')

@section('title', 'إدارة الفواتير')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

  <div class="ui-card    border ui-border rounded-3xl p-5 md:p-7 shadow-2xl shadow-black/20">
    <div class="flex flex-col gap-6">
    {{-- الحاوية العلوية: تجمع الأزرار في سطر واحد على الجوال --}}
    <div class="flex items-center justify-between w-full md:order-1">

        {{-- 1. زر الرجوع (يمين) --}}
        <div class="flex-shrink-0">
            <a href="{{ url()->previous() }}"
               class="ui-btn ui-btn-secondary px-3 py-2 md:px-4 md:py-2.5 ui-text-caption md:text-sm">
                <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span class="hidden xs:inline"> الرجوع للرئيسية</span>
                <span class="inline xs:hidden"> الرجوع للرئيسية</span>
            </a>
        </div>

        {{-- العنوان يظهر هنا فقط في الشاشات الكبيرة (اختياري حسب الرغبة) --}}
        <div class="hidden md:flex items-center gap-4 text-center justify-center">
            <h1 class="text-2xl font-black ui-title tracking-tight">إدارة الفواتير</h1>
        </div>

        {{-- 3. زر إنشاء فاتورة (يسار) --}}
        <div class="flex-shrink-0">
            <a href="{{ isset($store) ? route('user.stores.invoices.create', $store->id) : route('accountant.invoices.invoice.create') }}"
               class="ui-btn ui-btn-primary px-3 py-2 md:px-6 md:py-3 ui-text-caption md:text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                إنشاء فاتورة
            </a>
        </div>
    </div>

    {{-- 2. عنوان الصفحة: يظهر في المنتصف في سطر منفصل على الجوال --}}
    <div class="md:hidden flex flex-col items-center gap-2 text-center">
        <h1 class="text-xl font-black ui-title tracking-tight">إدارة الفواتير</h1>
        <p class="ui-text-muted ui-text-caption">لديك <span class="ui-status-info font-bold">{{ $invoices->total() }}</span> فاتورة مسجلة</p>
    </div>
    <div class="hidden md:flex justify-center">
        <p class="ui-text-muted text-sm ui-surface-muted-bg border ui-border rounded-xl px-4 py-2">
            لديك الآن <span class="ui-status-info font-extrabold">{{ $invoices->total() }}</span> فاتورة في هذا العرض.
        </p>
    </div>
    </div>
  </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="ui-surface-muted-bg border ui-border p-5 rounded-2xl transition-all">
            <span class="ui-text-muted ui-text-caption font-bold uppercase tracking-wider block mb-2">إجمالي الفواتير</span>
            <div class="text-2xl font-black ui-title">{{ $totalInvoices }}</div>
        </div>
        <div class="ui-surface-muted-bg border ui-border p-5 rounded-2xl transition-all">
            <span class="ui-status-success ui-text-caption font-bold uppercase tracking-wider block mb-2">المدفوعة</span>
            <div class="text-2xl font-black ui-status-success">{{ $paidInvoices }}</div>
        </div>
        <div class="ui-surface-muted-bg border ui-border p-5 rounded-2xl transition-all">
            <span class="ui-status-warning ui-text-caption font-bold uppercase tracking-wider block mb-2">المعلقة</span>
            <div class="text-2xl font-black ui-status-warning">{{ $pendingInvoices }}</div>
        </div>
        <div class="ui-surface-muted-bg border ui-border p-5 rounded-2xl transition-all">
            <span class="ui-status-info ui-text-caption font-bold uppercase tracking-wider block mb-2">إجمالي المبيعات</span>
            <div class="text-2xl font-black ui-status-info truncate">{{ number_format($totalAmount, 0) }} <small class="ui-text-caption">ر.س</small></div>
        </div>
    </div>

    <div class="ui-card p-6">
        <form method="GET" action="{{ isset($store) ? route('user.stores.invoices.index', $store->id) : route('accountant.invoices.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-7 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث برقم الفاتورة أو اسم العميل..."
                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl pr-12 py-3.5    transition-all outline-none">
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 ui-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="md:col-span-3">
               <input type="date" name="date" value="{{ request('date') }}"
                class="w-full ui-surface-muted-bg border ui-border ui-title rounded-xl px-4 py-3.5   outline-none"
               >
            </div>
            <div class="md:col-span-2 flex gap-2">
                <button type="submit" class="ui-btn ui-btn-primary flex-1">تطبيق</button>
                <a href="{{ isset($store) ? route('user.stores.invoices.index', $store->id) : route('accountant.invoices.index') }}" class="ui-btn ui-btn-secondary w-12 h-12" aria-label="مسح البحث">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </a>
            </div>
        </form>
    </div>

    @php
        $statusMeta = [
            'paid' => ['label' => 'مدفوعة', 'class' => 'ui-status-success-bg ui-status-success ui-border'],
            'pending' => ['label' => 'معلقة', 'class' => 'ui-status-warning-bg ui-status-warning ui-border'],
            'printed' => ['label' => 'مطبوعة', 'class' => 'ui-status-info-bg ui-status-info ui-border'],
            'canceled' => ['label' => 'ملغاة', 'class' => 'ui-status-danger-bg ui-status-danger ui-border'],
        ];
    @endphp

    <div class="ui-card overflow-hidden shadow-xl shadow-black/10">
        @if($invoices->total() > 0)

            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="ui-surface-muted-bg ui-text-muted ui-text-caption uppercase font-bold">
                            <th class="p-5">رقم الفاتورة</th>
                            <th class="p-5">العميل</th>
                            <th class="p-5">التاريخ</th>
                            <th class="p-5">المبلغ</th>
                            <th class="p-5 text-center">الحالة</th>
                            <th class="p-5 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ui-border">
                        @foreach($invoices as $invoice)
                        <tr class="ui-hover-info-bg transition-colors group">
                            <td class="p-5 font-mono font-bold ui-title uppercase text-sm">#{{ $invoice->invoice_number }}</td>
                            <td class="p-5">
                                <div class="ui-title font-bold text-sm">{{ $invoice->customer_name }}</div>
                                <div class="ui-text-muted ui-text-caption mt-1">{{ $invoice->customer_phone ?? 'بدون هاتف' }}</div>
                            </td>
                            <td class="p-5 ui-text-muted text-sm italic">{{ $invoice->created_at->format('Y/m/d') }}</td>
                            <td class="p-5">
                                <span class="ui-title font-black text-base">{{ number_format($invoice->total_amount, 2) }}</span>
                                <span class="ui-text-muted ui-text-caption">ر.س</span>
                            </td>
                            <td class="p-5 text-center">
                                @php($status = $statusMeta[$invoice->status] ?? ['label' => $invoice->status, 'class' => 'ui-surface-muted-bg ui-text-muted ui-border'])
                                <span class="px-3 py-1.5 rounded-lg border ui-text-caption font-black uppercase {{ $status['class'] }}">
                                    {{ $status['label'] }}
                                </span>
                            </td>
                            <td class="p-5">
                                <div class="flex items-center justify-center gap-2">
                                    {{-- رابط التفاصيل --}}
                                    <a href="{{ isset($store) ? route('user.stores.invoices.show', [$store->id, $invoice->id]) : route('accountant.invoices.show', $invoice->id) }}" class="ui-btn ui-btn-info p-2.5" title="عرض التفاصيل" aria-label="عرض تفاصيل الفاتورة">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    {{-- إصلاح مطبق: لكل فاتورة نموذج حذف مستقل يستخدم عقد التأكيد المركزي دون نموذج مخفي. --}}
                                    <form method="POST" action="{{ isset($store) ? route('user.stores.invoices.destroy', [$store->id, $invoice->id]) : route('accountant.invoices.destroy', $invoice->id) }}"
                                          data-ui-confirm="تنبيه: هل أنت متأكد تماماً من رغبتك في حذف هذه الفاتورة؟"
                                          data-ui-confirm-title="تأكيد حذف الفاتورة">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ui-btn ui-btn-danger p-2.5" title="حذف" aria-label="حذف الفاتورة">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="lg:hidden grid gap-3 p-3">
                @foreach($invoices as $invoice)
                <div class="p-4 space-y-4 rounded-2xl border ui-border ui-surface-muted-bg">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg ui-surface-muted-bg flex items-center justify-center font-bold ui-status-info ui-text-caption">#</div>
                            <div>
                                <h4 class="ui-title font-bold font-mono text-sm">#{{ $invoice->invoice_number }}</h4>
                                <span class="ui-text-muted ui-text-caption">{{ $invoice->created_at->format('d M Y | h:i A') }}</span>
                            </div>
                        </div>
                        @php($status = $statusMeta[$invoice->status] ?? ['label' => $invoice->status, 'class' => 'ui-surface-muted-bg ui-text-muted ui-border'])
                        <span class="px-2 py-1 rounded-lg border ui-text-caption font-black {{ $status['class'] }}">
                            {{ $status['label'] }}
                        </span>
                    </div>

                    <div class="ui-surface-muted-bg p-3 rounded-xl border ui-border space-y-2.5">
                        <div class="flex justify-between">
                            <span class="ui-text-muted ui-text-caption">العميل:</span>
                            <span class="ui-title ui-text-caption font-bold">{{ $invoice->customer_name }}</span>
                        </div>
                        <div class="flex justify-between items-baseline">
                            <span class="ui-text-muted ui-text-caption">المبلغ الإجمالي:</span>
                            <div class="text-left">
                                <span class="text-lg font-black ui-title">{{ number_format($invoice->total_amount, 2) }}</span>
                                <span class="ui-text-caption ui-text-muted mr-1">ر.س</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        {{-- رابط التفاصيل للجوال --}}
                        <a href="{{ isset($store) ? route('user.stores.invoices.show', [$store->id, $invoice->id]) : route('accountant.invoices.show', $invoice->id) }}" class="ui-btn ui-btn-info flex-1 py-2.5 ui-text-caption">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            تفاصيل
                        </a>
                        {{-- نموذج الحذف نفسه مكرر للجوال لأن نسخة سطح المكتب مخفية في هذا التخطيط. --}}
                        <form method="POST" action="{{ isset($store) ? route('user.stores.invoices.destroy', [$store->id, $invoice->id]) : route('accountant.invoices.destroy', $invoice->id) }}"
                              data-ui-confirm="تنبيه: هل أنت متأكد تماماً من رغبتك في حذف هذه الفاتورة؟"
                              data-ui-confirm-title="تأكيد حذف الفاتورة">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn-danger w-11 h-11 ui-text-caption" aria-label="حذف الفاتورة">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>

        @else
            <div class="py-24 text-center px-4">
                <div class="inline-flex w-20 h-20 ui-surface-muted-bg ui-text-muted rounded-full items-center justify-center mb-4">
                    <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3M7 7h10M7 11h4m-4 4h3"/>
                    </svg>
                </div>
                <h3 class="ui-title font-extrabold text-lg">لا توجد فواتير مطابقة لبحثك</h3>
                <p class="ui-text-muted text-sm mt-2">جرّب تغيير معايير البحث أو إعادة تعيين الفلاتر.</p>
            </div>
        @endif

        <div class="mt-6 px-2 pb-2">
            {{ $invoices->links() }}
        </div>
    </div>

</div>
@endsection
