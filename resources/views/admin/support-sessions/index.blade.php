@extends('dashboard.app')

@section('title', 'سجل جلسات الدعم التقني')

@section('content')
<div class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">سجل جلسات الدعم التقني</h1><button type="button" class="ui-help-btn" data-ui-help-title="قراءة سجل الجلسات" data-ui-help-body="يعرض السجل الحساب المستهدف وصفة الدخول وسبب الجلسة ورقم التذكرة ووقت البداية والنهاية. الجلسة النشطة تُنهى من شريط التنبيه الظاهر أثناء الدخول." aria-label="شرح سجل جلسات الدعم"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div>
            <p class="ui-text-soft mt-1">يوضح الحساب المستهدف، صفة الدخول، سبب الجلسة، رقم التذكرة، ووقت البداية والنهاية.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-secondary">اختيار حساب للدعم</a>
    </div>

    <form method="GET" class="ui-card grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
        <select name="status" class="ui-input" aria-label="حالة جلسة الدعم">
            <option value="">كل الجلسات</option>
            <option value="active" @selected(request('status') === 'active')>النشطة</option>
            <option value="ended" @selected(request('status') === 'ended')>المنتهية</option>
        </select>
        <button class="ui-btn ui-btn-primary" type="submit">تطبيق الفلترة</button>
        <a href="{{ route('admin.support.index') }}" class="ui-btn ui-btn-secondary">إلغاء الفلترة</a>
    </form>

    <div class="ui-table-wrap">
        <table class="ui-table min-w-[980px] text-right">
            <thead>
                <tr>
                    <th>الجلسة</th>
                    <th>الحساب المستهدف</th>
                    <th>السبب</th>
                    <th>المرجع</th>
                    <th>البداية</th>
                    <th>النهاية</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                    <tr>
                        <td>#{{ $session->id }}</td>
                        <td>
                            <div class="ui-title">{{ $session->target_name ?: 'حساب غير متاح' }}</div>
                            <span class="ui-badge ui-badge-info">{{ $session->target_role === 'accountant' ? 'محاسب' : 'مالك' }}</span>
                        </td>
                        <td class="max-w-sm whitespace-normal">{{ $session->reason }}</td>
                        <td>{{ $session->ticket_reference ?: '—' }}</td>
                        <td>{{ $session->started_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $session->ended_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            <span class="ui-badge {{ $session->ended_at ? 'ui-badge-neutral' : 'ui-badge-warning' }}">
                                {{ $session->ended_at ? 'منتهية' : 'نشطة' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center ui-text-muted">لا توجد جلسات دعم مطابقة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $sessions->links() }}
</div>
@endsection
