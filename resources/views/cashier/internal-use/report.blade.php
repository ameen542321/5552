@extends('dashboard.app')
@section('title', 'مشتريات المالك')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-right" dir="rtl">
    <div class="mb-6 ui-card    backdrop-blur-sm p-5 rounded-2xl border ui-border shadow-lg">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-extrabold ui-title tracking-tight">مشتريات المالك</h1>
                <div class="mt-1.5 flex items-center gap-2">
                    <p class="ui-text-soft font-bold">{{ $store->name ?? 'المتجر' }}</p>
                    <x-ui.help title="فصل السجلات" body="مشتريات المالك واستهلاك المحاسب منفصلان عن المصاريف المالية." />
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <a href="{{ route('user.stores.show', $storeId) }}" class="ui-btn ui-btn-secondary flex-1 sm:flex-none px-4 py-2.5 ui-text-caption">رجوع للمتجر</a>
                <a href="{{ route('user.stores.internal-use.trash', $storeId) }}" class="ui-btn ui-btn-secondary flex-1 sm:flex-none px-4 py-2.5 ui-text-caption">سلة المحذوفات</a>
                <a href="{{ route('user.stores.internal-use.export-pdf', ['store' => $storeId, 'month' => $month, 'year' => $year]) }}" class="ui-btn ui-btn-secondary flex-1 sm:flex-none px-4 py-2.5 ui-text-caption">تصدير PDF</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="ui-card p-5 flex flex-col justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="ui-title font-bold">تسجيل مشتريات المالك</h3>
                    <x-ui.help variant="warning" title="كيف يعمل هذا السجل؟" body="تظهر العملية في قسم مشتريات المالك، ولا تضاف إلى قائمة المصاريف. كما أنها لا تنقص كمية أي منتج من المخزون." />
                </div>
            </div>
            <button type="button" class="ui-btn ui-btn-primary w-full py-3" data-owner-purchase-create-open>تسجيل وإضافة مشتريات</button>
        </div>

        <div class="lg:col-span-2 ui-card p-5 flex flex-col justify-between">
            <div>
                <h3 class="ui-title font-bold text-sm mb-4 flex items-center gap-2">
                    <span class="p-1 ui-status-info-bg ui-status-info rounded-lg">📅</span> تحديد فترة التقرير
                </h3>
                <form method="GET" action="{{ route('user.stores.internal-use.report.view', $storeId) }}" class="space-y-3">
                    <div>
                        <label class="ui-text-caption ui-text-muted block mb-1">بحث في مشتريات المالك واستهلاك المحاسب</label>
                        <div class="relative">
                            <input type="search" name="search" value="{{ $search ?? request('search') }}" placeholder="ابحث بالنوع أو الملاحظات..." class="ui-input py-2 pl-9 pr-3 text-sm">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 ui-text-muted ui-text-caption"></i>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="ui-text-caption ui-text-muted block mb-1">الشهر</label>
                            <select name="month" class="ui-input py-2 px-3 text-sm">
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected((int) $month === $m)>{{ $m }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="ui-text-caption ui-text-muted block mb-1">السنة</label>
                            <input type="number" name="year" value="{{ $year }}" min="2020" max="2100" class="ui-input py-2 px-3 text-sm">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="ui-btn ui-btn-primary flex-1 py-2.5 ui-text-caption">تحديث/بحث</button>
                        @if(($search ?? '') !== '')
                            <a href="{{ route('user.stores.internal-use.report.view', ['store' => $storeId, 'month' => $month, 'year' => $year]) }}" class="ui-btn ui-btn-secondary py-2.5 px-3 ui-text-caption">مسح</a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="ui-section-divider ui-section-divider-sm text-center">
                <span class="ui-text-caption ui-text-muted">فترة التقرير الحالية:</span>
                <p class="ui-title font-mono font-bold ui-text-caption mt-1 ui-surface-muted-bg py-1 px-2 rounded-lg inline-block">{{ $reportData['startDate'] }} ➔ {{ $reportData['endDate'] }}</p>
            </div>
        </div>
    </div>

    @if(($ownerPurchaseGroups ?? collect())->count() > 0)
        <div class="mb-6 ui-surface-muted-bg p-4 rounded-2xl border ui-border">
            <h4 class="ui-text-muted font-bold ui-text-caption mb-3 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full ui-status-success-bg"></span> تجميع منتجات متطابقة
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach($ownerPurchaseGroups as $group)
                    <div class="ui-card p-2.5 flex items-center justify-between">
                        <div>
                            <p class="ui-status-success font-bold ui-text-caption">{{ $group['name'] }}</p>
                            <span class="ui-text-caption ui-text-muted">العمليات: {{ $group['count'] }}</span>
                        </div>
                        <p class="ui-status-warning font-extrabold ui-text-caption font-mono">{{ number_format($group['total'], 2) }} <span class="ui-text-caption font-normal ui-text-muted">ر.س</span></p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="ui-card   p-4 rounded-2xl border ui-border shadow-sm relative overflow-hidden">
            <div class="absolute left-4 top-4 text-2xl ui-status-info">🧾</div>
            <p class="ui-text-muted ui-text-caption font-medium">استهلاك المحاسب</p>
            <p class="ui-status-info text-xl font-black font-mono mt-2">{{ number_format($reportData['summary']['accountant_total'], 2) }} <span class="ui-text-caption font-bold ui-text-muted">ر.س</span></p>
        </div>
        <div class="ui-card   p-4 rounded-2xl border ui-border shadow-sm relative overflow-hidden">
            <div class="absolute left-4 top-4 text-2xl ui-status-success">🛒</div>
            <p class="ui-text-muted ui-text-caption font-medium">مشتريات المالك</p>
            <p class="ui-status-success text-xl font-black font-mono mt-2">{{ number_format($reportData['summary']['owner_total'], 2) }} <span class="ui-text-caption font-bold ui-text-muted">ر.س</span></p>
        </div>
        <div class="ui-card   p-4 rounded-2xl border ui-border shadow-sm relative overflow-hidden">
            <div class="absolute left-4 top-3 text-right">
                <span class="ui-status-warning-bg ui-status-warning ui-text-caption px-2 py-0.5 rounded-full font-bold">{{ number_format($reportData['summary']['count']) }} عملية</span>
            </div>
            <p class="ui-text-muted ui-text-caption font-medium">إجمالي المشتريات</p>
            <p class="ui-status-warning text-xl font-black font-mono mt-2">{{ number_format($reportData['summary']['grand_total'], 2) }} <span class="ui-text-caption font-bold ui-text-muted">ر.س</span></p>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="ui-title font-bold text-sm px-1 flex items-center gap-2">
            <span>📝</span> سجل العمليات والتفاصيل
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @forelse($reportData['records'] as $i => $row)
                <div class="ui-card p-4 flex flex-col justify-between gap-4 shadow-sm ui-border transition relative overflow-visible">
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="ui-text-caption font-mono ui-surface-muted-bg ui-text-muted w-6 h-6 rounded-lg flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                            <div class="min-w-0">
                                @if(str_contains($row['source'], 'المحاسب'))
                                    <span class="ui-status-info-bg ui-status-info ui-text-caption px-2 py-1 rounded-lg font-bold flex items-center gap-1 w-fit">
                                        <span>🧾</span> {{ $row['source'] }}
                                    </span>
                                @elseif(str_contains($row['source'], 'المالك'))
                                    <span class="ui-status-success-bg ui-status-success ui-text-caption px-2 py-1 rounded-lg font-bold flex items-center gap-1 w-fit">
                                        <span>🛒</span> {{ $row['source'] }}
                                    </span>
                                @else
                                    <span class="ui-surface-muted-bg ui-text-muted ui-text-caption px-2 py-1 rounded-lg font-bold">
                                        {{ $row['source'] }}
                                    </span>
                                @endif
                                <p class="ui-title font-bold text-sm mt-2 break-words">{{ $row['type'] }}</p>
                            </div>
                        </div>
                        <div class="text-left shrink-0">
                            <span class="ui-status-warning font-black text-base font-mono block">{{ number_format($row['amount'], 2) }}</span>
                            <span class="ui-text-caption ui-text-muted font-bold block">ر.س</span>
                        </div>
                    </div>

                    @if($row['description'] && $row['description'] !== '-')
                        <div class="ui-surface-muted-bg p-2.5 rounded-xl border ui-border">
                            <p class="ui-text-muted ui-text-caption leading-relaxed break-words">{{ $row['description'] }}</p>
                        </div>
                    @endif

                    {{-- التاريخ والأزرار عناصر تشغيلية داخل البطاقة ولا تحتاج إطارًا إضافيًا حولها. --}}
                    <div class="pt-3 flex items-center justify-between gap-3 ui-text-caption">
                        <span class="ui-text-muted font-mono ui-text-caption">
                            {{ \Carbon\Carbon::parse($row['operation_date'])->format('Y-m-d') }}
                        </span>

                        <div class="flex items-center gap-3 shrink-0">
                            @if(($row['entry_type'] ?? null) === 'owner_purchase')
                                <button type="button"
                                        class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption"
                                        data-owner-purchase-edit-open
                                        data-update-url="{{ route('user.stores.internal-use.add-consumption.update', ['store' => $storeId, 'purchase' => $row['entry_id']]) }}"
                                        data-type="{{ $row['type'] }}"
                                        data-amount="{{ $row['amount'] }}"
                                        data-description="{{ $row['description'] !== '-' ? $row['description'] : '' }}"
                                        data-business-date="{{ $row['business_date'] }}">تعديل</button>

                                {{-- إصلاح مطبق: تأكيدات حذف الاستهلاك موحدة عبر عقد الحوارات المركزي دون تغيير مسارات العملية. --}}
                                <form method="POST" action="{{ route('user.stores.internal-use.add-consumption.destroy', ['store' => $storeId, 'purchase' => $row['entry_id']]) }}"
                                      data-ui-confirm="هل أنت متأكد من حذف العملية؟"
                                      data-ui-confirm-title="تأكيد حذف العملية">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">حذف</button>
                                </form>
                            @elseif(($row['entry_type'] ?? null) === 'accountant_internal_use')
                                <details class="inline-block text-right relative dropdown-details" data-exclusive-details>
                                    <summary class="ui-btn ui-btn-secondary cursor-pointer select-none list-none px-3 py-2 ui-text-caption">تعديل</summary>
                                    <div class="absolute left-0 bottom-full mb-2 ui-card p-3 w-64 space-y-2 shadow-2xl z-20">
                                        <form method="POST" action="{{ route('user.stores.internal-use.accountant-consumption.update', ['store' => $storeId, 'sale' => $row['entry_id']]) }}" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="ui-text-caption ui-text-muted block mb-0.5">الكمية</label>
                                                <input type="number" step="0.01" min="0.01" name="quantity" value="{{ $row['raw_quantity'] ?? 1 }}" class="ui-input px-2 py-2 ui-text-caption" required>
                                            </div>
                                            <div>
                                                <label class="ui-text-caption ui-text-muted block mb-0.5">نوع الوحدة</label>
                                                <select name="unit_type" class="ui-input px-2 py-2 ui-text-caption">
                                                    @php($unitType = $row['raw_unit_type'] ?? 'default')
                                                    <option value="default" @selected($unitType === 'default')>افتراضي</option>
                                                    <option value="meters" @selected($unitType === 'meters')>متر</option>
                                                    <option value="roll" @selected($unitType === 'roll')>رول</option>
                                                    <option value="piece" @selected($unitType === 'piece')>حبة</option>
                                                    <option value="kit" @selected($unitType === 'kit')>طقم</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="ui-text-caption ui-text-muted block mb-0.5">الملاحظات</label>
                                                <textarea name="internal_notes" rows="2" class="ui-input px-2 py-2 ui-text-caption" placeholder="ملاحظات">{{ $row['description'] !== '-' ? $row['description'] : '' }}</textarea>
                                            </div>
                                            <button type="submit" class="ui-btn ui-btn-primary w-full py-2 ui-text-caption">تعديل استهلاك المحاسب</button>
                                        </form>
                                    </div>
                                </details>

                                <form method="POST" action="{{ route('user.stores.internal-use.accountant-consumption.destroy', ['store' => $storeId, 'sale' => $row['entry_id']]) }}"
                                      data-ui-confirm="هل تريد حذف العملية واسترجاع المخزون؟"
                                      data-ui-confirm-title="تأكيد الحذف والاسترجاع">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">حذف واسترجاع</button>
                                </form>
                            @else
                                <span class="ui-text-muted">-</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-1 md:col-span-2 ui-surface-muted-bg text-center py-12 rounded-2xl border border-dashed ui-border">
                    <p class="ui-text-muted text-sm">لا توجد مشتريات أو عمليات استهلاك محاسب مسجلة في هذا الشهر.</p>
                </div>
            @endforelse
        </div>

        @if(method_exists($reportData['records'], 'links') && $reportData['records']->hasPages())
            <div class="mt-6">
                {{ $reportData['records']->links() }}
            </div>
        @endif
    </div>
</div>

<datalist id="ownerPurchaseTypes">
    @foreach(($ownerPurchaseTypeOptions ?? []) as $option)<option value="{{ $option }}"></option>@endforeach
</datalist>

<div class="ui-modal-backdrop hidden" data-owner-purchase-create-modal role="dialog" aria-modal="true" aria-labelledby="owner-purchase-create-title">
    <div class="ui-modal-panel ui-modal-panel-transfer">
        <div class="ui-modal-header">
            <div class="flex items-center gap-2">
                <h2 id="owner-purchase-create-title" class="ui-title font-bold text-lg">تسجيل مشتريات المالك</h2>
                <x-ui.help variant="warning" title="أثر العملية" body="تظهر العملية في قسم مشتريات المالك بتاريخها المحدد، ولا تنقص كمية أي منتج في المخزون." />
            </div>
            <button type="button" class="ui-modal-close-text-danger" data-owner-purchase-modal-close>إغلاق</button>
        </div>
        <form method="POST" action="{{ route('user.stores.internal-use.add-consumption.store', $storeId) }}" class="p-5 space-y-4">
            @csrf
            <div><label class="ui-text-soft font-bold block mb-2">اختر التاريخ</label><input type="date" name="business_date" value="{{ old('business_date', $defaultBusinessDate) }}" max="{{ $defaultBusinessDate }}" required class="ui-input px-4 py-3"></div>
            <div><label class="ui-text-soft font-bold block mb-2">نوع المشتريات</label><input type="text" name="type" value="{{ old('type') }}" list="ownerPurchaseTypes" required class="ui-input px-4 py-3" placeholder="مثال: أمواس أو ربل أو تضليل"></div>
            <div><label class="ui-text-soft font-bold block mb-2">المبلغ</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="ui-input px-4 py-3" placeholder="0.00"></div>
            <div><label class="ui-text-soft font-bold block mb-2">ملاحظات</label><textarea name="description" rows="3" class="ui-input px-4 py-3" placeholder="تفاصيل إضافية عن المشتريات">{{ old('description') }}</textarea></div>
            <button type="submit" class="ui-btn ui-btn-primary w-full py-3">حفظ العملية</button>
        </form>
    </div>
</div>

<div class="ui-modal-backdrop hidden" data-owner-purchase-edit-modal role="dialog" aria-modal="true" aria-labelledby="owner-purchase-edit-title">
    <div class="ui-modal-panel ui-modal-panel-transfer">
        <div class="ui-modal-header"><h2 id="owner-purchase-edit-title" class="ui-title font-bold text-lg">تعديل مشتريات المالك</h2><button type="button" class="ui-modal-close-text-danger" data-owner-purchase-modal-close>إغلاق</button></div>
        <form method="POST" data-owner-purchase-edit-form class="p-5 space-y-4">
            @csrf @method('PUT')
            <div><label class="ui-text-soft font-bold block mb-2">اختر التاريخ</label><input type="date" name="business_date" max="{{ $defaultBusinessDate }}" required class="ui-input px-4 py-3" data-owner-purchase-edit-date></div>
            <div><label class="ui-text-soft font-bold block mb-2">النوع</label><input type="text" name="type" list="ownerPurchaseTypes" required class="ui-input px-4 py-3" data-owner-purchase-edit-type></div>
            <div><label class="ui-text-soft font-bold block mb-2">المبلغ</label><input type="number" step="0.01" min="0.01" name="amount" required class="ui-input px-4 py-3" data-owner-purchase-edit-amount></div>
            <div><label class="ui-text-soft font-bold block mb-2">الملاحظات</label><textarea name="description" rows="3" class="ui-input px-4 py-3" data-owner-purchase-edit-description></textarea></div>
            <button type="submit" class="ui-btn ui-btn-primary w-full py-3">حفظ التعديل</button>
        </form>
    </div>
</div>

@endsection
