@php
    $isEdit = isset($store) && $store->exists;
@endphp
<div class="max-w-7xl mx-auto px-4 py-8" data-store-management>

    {{-- Header Section --}}
    <div class="ui-card p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 ui-surface-strong-bg rounded-xl flex items-center justify-center">
                <i class="fa-solid {{ $isEdit ? 'fa-pen-to-square' : 'fa-store' }} ui-title text-2xl"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl md:text-3xl font-extrabold ui-title">
                    {{ $isEdit ? 'تعديل المتجر: ' . $store->name : 'إضافة متجر جديد' }}
                </h1>
                @unless($isEdit)
                    <p class="ui-text-muted text-sm mt-1">إعداد البيانات وفقاً لهيكلية النظام</p>
                @endunless
            </div>
        </div>

        <button type="button" data-history-back
                class="inline-flex items-center gap-3 ui-surface-muted-bg ui-hover-info-bg ui-text-muted px-6 py-3 rounded-2xl border ui-border transition-all group font-semibold backdrop-blur-sm">
            <i class="fa-solid fa-chevron-right transition-transform group-hover:translate-x-1"></i>
            <span>رجوع</span>
        </button>
    </div>

    <form action="{{ $isEdit ? route('user.stores.update', $store->id) : route('user.stores.store') }}"
                  method="POST" enctype="multipart/form-data" class="space-y-8">

                @csrf
                @if($isEdit) @method('PUT') @endif
                @if($isEdit && request('return_to'))
                    <input type="hidden" name="return_to" value="{{ request('return_to') }}">
                @endif

                {{-- بطاقة الهوية التجارية --}}
                <div class="ui-surface-muted-bg border ui-border rounded-3xl shadow-2xl overflow-hidden backdrop-blur-md">
                    <div class="p-8 border-b ui-border ui-card to-transparent">
                        <h2 class="ui-title text-xl font-bold flex items-center gap-3">
                            <i class="fa-solid fa-id-card ui-status-info"></i>
                            الهوية التجارية
                        </h2>
                    </div>

                    <div class="p-8 space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="space-y-2">
                                <label class="ui-text-muted text-sm font-semibold mr-1">اسم المتجر</label>
                                <input type="text" name="name" value="{{ old('name', $store->name ?? '') }}"
                                       class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 transition-all outline-none" required>
                            </div>

                            <div class="space-y-2">
                                <label class="ui-text-muted text-sm font-semibold mr-1">شعار المتجر</label>
                                <input type="file" name="logo" class="w-full ui-surface-muted-bg border ui-border ui-text-muted rounded-2xl px-5 py-2.5 transition-all outline-none">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">الوصف</label>
                            <textarea name="description" rows="3" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3 transition-all outline-none">{{ old('description', $store->description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- بطاقة البيانات القانونية والبنكية. --}}
                <div class="ui-surface-muted-bg border ui-border rounded-3xl shadow-2xl overflow-hidden backdrop-blur-md">
                    <div class="p-8 border-b ui-border ui-card to-transparent">
                        <h2 class="ui-title text-xl font-bold flex items-center gap-3">
                            <i class="fa-solid fa-file-contract ui-status-success"></i>
                            البيانات القانونية والبنكية
                        </h2>
                    </div>

                    <div class="p-8 grid grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">السجل التجاري</label>
                            <input type="text" name="commercial_registration" value="{{ old('commercial_registration', $store->commercial_registration ?? '') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">الرقم الضريبي</label>
                            <input type="text" name="tax_number" value="{{ old('tax_number', $store->tax_number ?? '') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">رقم الهاتف</label>
                            <input type="text" name="phone" value="{{ old('phone', $store->phone ?? '') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 outline-none text-left" dir="ltr">
                        </div>

                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">العنوان</label>
                            <input type="text" name="address" value="{{ old('address', $store->address ?? '') }}" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 outline-none">
                        </div>

                        <div class="md:col-span-2 space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">الحسابات البنكية (IBAN)</label>
                            <textarea name="bank_accounts" rows="3" class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3 outline-none italic">{{ old('bank_accounts', $store->bank_accounts ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- إعدادات البيع السريع --}}
                @php
                    $laborOptions = collect(old('labor_description_options', $store->labor_description_options_list ?? ['تضليل', 'تجليد', 'شغل يد']))->pad(6, '')->take(6);
                @endphp
                <div class="ui-card p-6 space-y-5">
                    <div class="flex items-center gap-2">
                        <h2 class="ui-title text-xl font-bold">إعدادات البيع السريع</h2>
                        <x-ui.help title="خيارات أجور العمل" body="هذه الخيارات تظهر كأزرار جاهزة داخل حقل وصف أجور العمل. اكتب كل خيار في حقل مستقل، وبحد أقصى 6 خيارات." />
                        <x-ui.help variant="warning" title="القيم الافتراضية" body="القيم الافتراضية عند ترك الحقول فارغة: تضليل، تجليد، شغل يد. سيتم اعتماد أول 6 خيارات فقط." />
                    </div>
                    <div class="space-y-3">
                        @foreach($laborOptions as $option)
                            <div>
                                <label class="ui-text-caption ui-text-soft font-bold">الخيار {{ $loop->iteration }}</label>
                                <input type="text" name="labor_description_options[]" value="{{ $option }}" maxlength="100" class="ui-input mt-1 px-4 py-3">
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- إعدادات الشفتات --}}
                <div class="ui-surface-muted-bg border ui-border rounded-3xl shadow-2xl overflow-hidden backdrop-blur-md">
                    <div class="p-8 border-b ui-border ui-card to-transparent">
                        <h2 class="ui-title text-xl font-bold flex items-center gap-3">
                            <i class="fa-solid fa-clock-rotate-left ui-status-warning"></i>
                            إعدادات الشفتات
                        </h2>
                        <x-ui.help variant="warning" title="إعداد الشفتات" body="هذا الإعداد يؤثر على فتح الشفت الثاني." />
                    </div>

                    <div class="p-8 space-y-4" x-data="{ selectedShifts: '{{ (string) old('number_of_shifts', $store->number_of_shifts ?? 1) }}' }">
                        <div class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">عدد الشفتات المسموحة في اليوم</label>
                            <select name="number_of_shifts" required x-model="selectedShifts"
                                    class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 transition-all outline-none">
                                <option value="1">شفت واحد فقط</option>
                                <option value="2">شفتان في نفس اليوم عند الحاجة</option>
                            </select>
                        </div>

                        <div x-cloak x-show="selectedShifts === '2'" class="rounded-2xl border ui-border ui-status-warning-bg p-4 ui-status-warning ui-text-caption leading-6">
                            <i class="fa-solid fa-triangle-exclamation ml-1"></i>
                            إذا تم اختيار شفتين، سيظهر للمحاسب عند إغلاق الشفت الأول خيار فتح شفت ثانٍ لنفس التاريخ أو الانتقال لتاريخ العمل التالي.
                        </div>
                    </div>
                </div>

                {{-- إعدادات جرد المنتجات --}}
                <div class="ui-surface-muted-bg border ui-border rounded-3xl shadow-2xl overflow-hidden backdrop-blur-md">
                    <div class="p-8 border-b ui-border ui-card to-transparent">
                        <h2 class="ui-title text-xl font-bold flex items-center gap-3">
                            <i class="fa-solid fa-clipboard-check ui-status-info"></i>
                            إعدادات جرد المنتجات
                        </h2>
                        <x-ui.help title="دورة الجرد" body="تحدد هذه الخيارات دورة جرد المنتجات وبداية احتساب دورة الجرد الحالية. الافتراضي: كل 6 أشهر من تاريخ إنشاء المتجر." />
                    </div>

                    <div class="p-8 space-y-6"
                         x-data="{ auditStartMode: '{{ old('inventory_audit_start_mode', $store->inventory_audit_start_mode ?? 'store_created_at') }}' }">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="space-y-2">
                                <label class="ui-text-muted text-sm font-semibold mr-1">دورة جرد المنتجات</label>
                                <select name="inventory_audit_cycle_months" required
                                        class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 transition-all outline-none">
                                    <option value="6" @selected((int) old('inventory_audit_cycle_months', $store->inventory_audit_cycle_months ?? 6) === 6)>كل 6 أشهر (افتراضي)</option>
                                    <option value="12" @selected((int) old('inventory_audit_cycle_months', $store->inventory_audit_cycle_months ?? 6) === 12)>كل 12 شهر</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="ui-text-muted text-sm font-semibold mr-1">تاريخ بداية الجرد</label>
                                <select name="inventory_audit_start_mode" required x-model="auditStartMode"
                                        class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 transition-all outline-none">
                                    <option value="store_created_at">تاريخ إنشاء المتجر</option>
                                    <option value="manual">تاريخ يدوي</option>
                                </select>
                            </div>
                        </div>

                        <div x-cloak x-show="auditStartMode === 'manual'" class="space-y-2">
                            <label class="ui-text-muted text-sm font-semibold mr-1">التاريخ اليدوي لبداية الجرد</label>
                            <input type="date" name="inventory_audit_start_date"
                                   value="{{ old('inventory_audit_start_date', optional($store->inventory_audit_start_date ?? null)->format('Y-m-d')) }}"
                                   class="w-full ui-surface-muted-bg border ui-border ui-title rounded-2xl px-5 py-3.5 transition-all outline-none">
                            <p class="ui-text-caption ui-text-muted">إذا اخترت تاريخ إنشاء المتجر، سيتم تجاهل هذا الحقل تلقائياً.</p>
                        </div>
                    </div>
                </div>

                {{-- زر الإرسال --}}
                <button type="submit" class="w-full h-16 ui-card ui-title rounded-2xl font-black text-lg shadow-2xl transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-3">
                    <i class="fa-solid {{ $isEdit ? 'fa-rotate' : 'fa-circle-check' }}"></i>
                    {{ $isEdit ? 'تحديث بيانات المتجر' : 'تأكيد وإنشاء المتجر' }}
                </button>
    </form>
</div>
