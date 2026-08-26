@extends('dashboard.app')
@section('title', ' الموظفين المحذوفين ')

@section('content')

<div class="px-6 py-8">

    <!-- الهيدر -->
    <div class="flex items-center justify-between mb-10">
        <div>
            <h1 class="text-3xl font-bold ui-title">سلة الموظفين المحذوفين</h1>
            <p class="ui-text-muted text-sm mt-1">هذه السلة تعرض الموظفين المحذوفين. إذا حُذف حساب محاسب مستقل فسيظهر ضمن إدارة المحاسبين عند تفعيل مسارها.</p>
        </div>

      @php
    // تحديد رابط العودة الافتراضي
    $backUrl = request('return_to') ?: route('user.employees.index');

    // إذا كان المستخدم جاء من صفحة متجر محدد ولم يرسل رابط رجوع كامل
    if(! request('return_to') && request('from') == 'store' && request('store_id')) {
        $backUrl = route('user.stores.employees.index', request('store_id'));
    }
@endphp

<div class="mb-6">
    <a href="{{ $backUrl }}" 
       class="inline-flex items-center gap-2 px-4 py-2 ui-card ui-text-soft rounded-xl ui-hover-info-bg ui-hover-info transition-all shadow-sm group">
        <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
        <span class="font-bold text-sm">رجوع</span>
    </a>
</div>
    </div>

    <!-- بطاقة إحصائية -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        <div class="ui-card p-6 flex items-center gap-4 shadow hover:shadow-lg transition">
            <div class="ui-status-danger-bg ui-status-danger p-3 rounded-lg">
                <i class="fa-solid fa-trash text-2xl"></i>
            </div>
            <div>
                <p class="ui-text-muted text-sm">عدد الموظفين المحذوفين</p>
                <p class="text-2xl font-bold ui-title">{{ $employees->total() }}</p>
            </div>
        </div>

    </div>

    <!-- الجدول -->
    <div class="ui-card overflow-hidden shadow-xl">

        <table class="w-full text-right">
            <thead class="ui-card-b ui-border">
                <tr class="ui-text-muted text-sm">
                    <th class="p-4">الموظف</th>
                    <th class="p-4">المتجر</th>
                    <th class="p-4">تاريخ الحذف</th>
                    <th class="p-4 text-center">إجراءات</th>
                </tr>
            </thead>

            <tbody class="ui-text-soft">

                @forelse ($employees as $employee)
                    <tr class="border-b ui-border ui-hover-info-bg transition">

                        <!-- الاسم -->
                        <td class="p-4 font-semibold flex items-center gap-3">
                            <div class="ui-btn ui-btn-secondary ui-text-soft w-10 h-10 rounded-full flex items-center justify-center shadow-inner">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <span>{{ $employee->name }}</span>
                                @if($employee->archivedItem?->status === 'archived')
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="ui-badge ui-badge-warning">محذوف من الحساب</span>
                                        <span class="ui-badge ui-badge-neutral">{{ $employee->archivedItem->reference }}</span>
                                    </div>
                                    <div class="ui-text-muted mt-1 text-sm">حتى {{ $employee->archivedItem->owner_restore_deadline?->format('Y-m-d H:i') }}</div>
                                    @if($employee->archivedItem->admin_message)
                                        <div class="ui-alert ui-alert-warning mt-2">{{ $employee->archivedItem->admin_message }}</div>
                                    @endif
                                @endif
                            </div>
                            @if($employee->archivedItem?->status === 'archived')
                                <form method="POST" action="{{ route('admin.support.archive.message', $employee->archivedItem) }}" class="mt-3 space-y-2">
                                    @csrf @method('PATCH')
                                    <textarea name="admin_message" required minlength="5" maxlength="2000" rows="2" class="ui-input w-full" placeholder="رسالة الدعم أو سبب تعذر الاستعادة">{{ $employee->archivedItem->admin_message }}</textarea>
                                    <button type="submit" class="ui-btn ui-btn-secondary">حفظ رسالة الدعم</button>
                                </form>
                            @endif
                        </td>

                        <!-- المتجر -->
                        <td class="p-4">
                            {{ $employee->store->name ?? '—' }}
                        </td>

                        <!-- تاريخ الحذف -->
                        <td class="p-4 ui-text-muted text-sm">
                            {{ $employee->deleted_at->format('Y-m-d H:i') }}
                        </td>

                        <!-- الإجراءات -->
                        <td class="p-4">
                            <div class="flex items-center justify-center gap-6">

                                <!-- استرجاع -->
                                <form action="{{ route('user.employees.restore', $employee->id) }}" method="POST">
                                    @csrf
                                    <button class="ui-status-success ui-hover-info transition" title="استرجاع">
                                        <i class="fa-solid fa-rotate-left text-xl"></i>
                                    </button>
                                </form>

                                <!-- حذف نهائي -->
                                {{-- إصلاح مطبق: الحذف النهائي يستخدم تأكيد SweetAlert المشترك مع الاحتياط المركزي. --}}
                                <form action="{{ route('user.employees.forceDelete', $employee->id) }}"
                                      method="POST"
                                      data-ui-confirm="{{ $employee->archivedItem?->status === 'archived' ? 'سيحذف الدعم الموظف فعليًا فقط إذا لم تكن له أي سجلات مرتبطة.' : 'سيختفي الموظف من حسابك ويمكن طلب استعادته خلال 30 يومًا.' }}"
                                      data-ui-confirm-title="تأكيد الحذف النهائي">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ui-status-danger ui-hover-info transition" title="{{ $employee->archivedItem?->status === 'archived' ? 'حذف فعلي بواسطة الدعم' : 'حذف نهائي' }}">
                                        <i class="fa-solid fa-trash text-xl"></i>
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>

                @empty
                    <tr>
                        <td colspan="4" class="p-10 text-center ui-text-muted">
                            لا يوجد موظفين محذوفين
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>

    </div>

    <!-- الباجينيشن -->
    <div class="mt-6 ui-text-muted">
        {{ $employees->links() }}
    </div>

</div>

@endsection
