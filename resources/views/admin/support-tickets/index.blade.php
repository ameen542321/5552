@extends('dashboard.app')
@section('title', 'طلبات الدعم')
@section('content')
<div class="mx-auto max-w-7xl space-y-5 p-4 sm:p-6" dir="rtl">
    <div><div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">طلبات الدعم التقني</h1><button type="button" class="ui-help-btn" data-ui-help-title="إدارة طلبات الدعم" data-ui-help-body="راجع الطلب، أرسل الرد الأول، ثم ابدأ جلسة الدخول عند الحاجة. التذكرة النشطة لا تُحذف؛ يجب إنهاؤها أولًا لتنتقل إلى صفحة إجراءات." aria-label="شرح إدارة طلبات الدعم"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div><p class="ui-text-soft mt-1">كل دخول أو رد أو عملية دعم مرتبطة برقم تذكرة ظاهر في السجلات.</p></div>
    <form method="GET" class="ui-card grid grid-cols-1 gap-3 p-4 sm:grid-cols-3"><input name="search" value="{{ request('search') }}" class="ui-input" placeholder="رقم التذكرة أو المالك"><select name="status" class="ui-input"><option value="">كل الحالات</option><option value="waiting_support" @selected(request('status') === 'waiting_support')>بانتظار الدعم التقني</option><option value="waiting_owner" @selected(request('status') === 'waiting_owner')>بانتظار المالك</option><option value="in_progress" @selected(request('status') === 'in_progress')>قيد التنفيذ</option><option value="closed" @selected(request('status') === 'closed')>مغلقة</option><option value="cancelled" @selected(request('status') === 'cancelled')>ملغاة لانتهاء المهلة</option></select><button class="ui-btn ui-btn-primary">تطبيق</button></form>
    <div class="space-y-4">
        @forelse($tickets as $ticket)
            <article class="ui-card p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div><div class="ui-title text-lg font-bold">{{ $ticket->subject }}</div><div class="ui-text-muted">{{ $ticket->reference }} — {{ $ticket->owner?->name ?: 'حساب غير متاح' }} — {{ $ticket->category_label }} — {{ $ticket->priority_label }}</div></div><div class="flex items-center gap-2"><span class="ui-badge ui-badge-info">{{ $ticket->status_label }}</span>@if($ticket->support_unread_count)<span class="ui-badge ui-badge-warning">{{ $ticket->support_unread_count }} جديد</span>@endif</div></div>
                <p class="ui-text-soft mt-3 whitespace-pre-line">{{ $ticket->description }}</p>@if(!$ticket->responded_at && $ticket->expires_at)<p class="ui-text-muted mt-2">مهلة الرد: {{ $ticket->expires_at->format('Y-m-d H:i') }}</p>@endif
                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[auto_auto_1fr_auto]">
                    <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="ui-btn ui-btn-secondary">التفاصيل</a>
                    @if(!in_array($ticket->status, ['closed', 'cancelled'], true))
                        <form method="POST" action="{{ route('admin.support-tickets.start', $ticket) }}">@csrf<button class="ui-btn ui-btn-warning w-full" type="submit">دخول</button></form>
                        <form method="POST" action="{{ route('admin.support-tickets.respond', $ticket) }}" class="flex gap-2">@csrf @method('PATCH')<input name="support_response" class="ui-input min-w-0 flex-1" required placeholder="رد الدعم التقني"><button class="ui-btn ui-btn-primary">رد</button></form>
                        <span class="ui-text-muted">أغلق التذكرة قبل حذفها.</span>
                    @else
                        <span class="ui-text-muted">العملية منتهية.</span>
                        <span></span>
                        <form method="POST" action="{{ route('admin.support-tickets.destroy', $ticket) }}" data-ui-confirm="ستُنقل التذكرة المنتهية إلى المحذوفات." data-ui-confirm-title="حذف طلب الدعم؟">@csrf @method('DELETE')<button class="ui-btn ui-btn-danger w-full">حذف</button></form>
                    @endif
                </div>
            </article>
        @empty<div class="ui-card p-8 text-center ui-text-muted">لا توجد طلبات دعم مطابقة.</div>@endforelse
    </div>
    {{ $tickets->links() }}
</div>
@endsection
