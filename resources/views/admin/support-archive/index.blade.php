@extends('dashboard.app')

@section('title', 'سجل المحذوفات')

@section('content')
<div class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">سجل المحذوفات</h1><button type="button" class="ui-help-btn" data-ui-help-title="مراجعة سجل المحذوفات" data-ui-help-body="استخدم الفلاتر للوصول إلى العملية، ثم اختر بدء المراجعة. سيتحقق النظام من المالك والمتجر، وينشئ تذكرة وجلسة دعم، ويفتح سلة المحذوفات المناسبة." aria-label="شرح سجل المحذوفات"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div>
            <p class="ui-text-soft mt-1">مراجعة العناصر التي أخفاها المالك، ومتابعة مهلة الاستعادة قبل الحذف الفعلي.</p>
        </div>
        <a href="{{ route('admin.support.index') }}" class="ui-btn ui-btn-secondary">سجل جلسات الدعم</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="ui-card p-4"><div class="ui-text-muted">بانتظار المعالجة</div><div class="ui-title mt-1 text-2xl font-bold">{{ $summary['active'] }}</div></div>
        <div class="ui-card p-4"><div class="ui-text-muted">انتهت مهلة الاستعادة</div><div class="ui-status-warning mt-1 text-2xl font-bold">{{ $summary['expired'] }}</div></div>
        <div class="ui-card p-4"><div class="ui-text-muted">تمت استعادتها</div><div class="ui-status-success mt-1 text-2xl font-bold">{{ $summary['restored'] }}</div></div>
        <div class="ui-card p-4"><div class="ui-text-muted">حُذفت فعليًا</div><div class="ui-title mt-1 text-2xl font-bold">{{ $summary['purged'] }}</div></div>
    </div>

    <form method="GET" class="ui-card grid grid-cols-1 gap-3 p-4 md:grid-cols-5">
        <input name="search" value="{{ request('search') }}" class="ui-input" placeholder="المرجع، العنصر أو المالك" aria-label="البحث في سجل المحذوفات">
        <select name="status" class="ui-input" aria-label="حالة السجل">
            <option value="">كل الحالات</option>
            <option value="archived" @selected(request('status') === 'archived')>بانتظار المعالجة</option>
            <option value="restored" @selected(request('status') === 'restored')>مستعاد</option>
            <option value="purged" @selected(request('status') === 'purged')>محذوف فعليًا</option>
        </select>
        <select name="type" class="ui-input" aria-label="نوع العنصر المحذوف">
            <option value="">كل الأنواع</option>
            @foreach($types as $type)
                @php($label = (new \App\Models\ArchivedItem(['archivable_type' => $type]))->type_label)
                <option value="{{ $type }}" @selected(request('type') === $type)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="deadline" class="ui-input" aria-label="مهلة الاستعادة">
            <option value="">كل المهل</option>
            <option value="active" @selected(request('deadline') === 'active')>المهلة سارية</option>
            <option value="expired" @selected(request('deadline') === 'expired')>المهلة منتهية</option>
        </select>
        <div class="flex gap-2">
            <button class="ui-btn ui-btn-primary flex-1" type="submit">تطبيق</button>
            <a class="ui-btn ui-btn-secondary" href="{{ route('admin.support.archive.index') }}">مسح</a>
        </div>
    </form>

    <div class="ui-table-wrap">
        <table class="ui-table min-w-[1100px] text-right">
            <thead>
                <tr>
                    <th>المرجع</th>
                    <th>العنصر</th>
                    <th>المالك والمتجر</th>
                    <th>تاريخ الحذف من الحساب</th>
                    <th>مهلة الاستعادة</th>
                    <th>الحالة</th>
                    <th>رسالة الدعم</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($archives as $archive)
                    <tr>
                        <td class="font-semibold">{{ $archive->reference }}</td>
                        <td>
                            <div class="ui-title font-semibold">{{ $archive->original_name ?: 'سجل #' . $archive->archivable_id }}</div>
                            <span class="ui-badge ui-badge-info">{{ $archive->type_label }}</span>
                        </td>
                        <td>
                            <div class="ui-title">{{ $archive->owner?->name ?: 'مالك غير متاح' }}</div>
                            <div class="ui-text-muted text-sm">{{ $archive->store?->name ?: 'دون متجر محدد' }}</div>
                        </td>
                        <td>{{ $archive->archived_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            <span class="ui-badge {{ $archive->restore_window_expired ? 'ui-badge-warning' : 'ui-badge-neutral' }}">
                                {{ $archive->owner_restore_deadline?->format('Y-m-d H:i') ?: 'غير محددة' }}
                            </span>
                        </td>
                        <td>
                            <span class="ui-badge {{ $archive->status === 'archived' ? 'ui-badge-warning' : ($archive->status === 'restored' ? 'ui-badge-success' : 'ui-badge-neutral') }}">
                                {{ $archive->status === 'archived' ? 'بانتظار المعالجة' : ($archive->status === 'restored' ? 'مستعاد' : 'محذوف فعليًا') }}
                            </span>
                        </td>
                        <td class="max-w-sm whitespace-normal">{{ $archive->admin_message ?: 'لا توجد رسالة من الدعم.' }}</td>
                        <td>
                            @if($archive->owner && $archive->status === 'archived')
                                <form method="POST" action="{{ route('admin.support.archive.review', $archive) }}" data-ui-confirm="سيبدأ النظام جلسة دعم مرتبطة بتذكرة جديدة ويفتح سلة المحذوفات المناسبة." data-ui-confirm-title="بدء مراجعة العملية؟">
                                    @csrf
                                    <button type="submit" class="ui-btn ui-btn-primary">بدء المراجعة</button>
                                </form>
                            @elseif($archive->owner)
                                <a href="{{ route('admin.users.show', $archive->owner) }}" class="ui-btn ui-btn-secondary">فتح حساب المالك</a>
                            @else
                                <span class="ui-text-muted">الحساب غير متاح</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center ui-text-muted">لا توجد سجلات محذوفات مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $archives->links() }}
</div>
@endsection
