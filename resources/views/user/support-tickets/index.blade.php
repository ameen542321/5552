@extends('dashboard.app')
@section('title', 'طلبات الدعم')
@section('content')
<div class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6" dir="rtl">
    <div><div class="flex items-center gap-2"><h1 class="ui-title text-2xl font-bold">إنشاء طلب دعم فني</h1><button type="button" class="ui-help-btn" data-ui-help-title="طريقة إنشاء طلب دعم" data-ui-help-body="حدد الحساب المطلوب مراجعته، ثم اختر التصنيف والأولوية، واكتب المشكلة والنتيجة المطلوبة بوضوح. سيصدر النظام رقم تذكرة للمتابعة؛ لا ترسل كلمة المرور أو أي رمز دخول ضمن الطلب." aria-label="شرح إنشاء طلب الدعم"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></button></div><p class="ui-text-soft mt-1">حدد الحساب، صنّف المشكلة، ثم اكتب المطلوب والنتيجة المتوقعة بوضوح.</p></div>
    <form method="POST" action="{{ route('user.support-tickets.store') }}" class="ui-card grid grid-cols-1 gap-4 p-5 md:grid-cols-2" x-data="{ accountType: '{{ old('requested_role', 'owner') }}' }">
        @csrf
        <div class="md:col-span-2"><label class="ui-text-soft mb-2 block">حدد الحساب</label><div class="grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="ui-card-muted flex cursor-pointer items-center gap-3 p-4"><input type="radio" name="requested_role" value="owner" x-model="accountType" required><span class="ui-title font-bold">مالك متجر</span></label><label class="ui-card-muted flex cursor-pointer items-center gap-3 p-4"><input type="radio" name="requested_role" value="accountant" x-model="accountType" required><span class="ui-title font-bold">محاسب</span></label></div></div>
        <div class="md:col-span-2" x-show="accountType === 'accountant'" x-cloak><label class="ui-text-soft mb-2 block">اختر المحاسب</label><div class="grid grid-cols-1 gap-2 sm:grid-cols-2">@forelse($accountants as $accountant)<label class="ui-card-muted flex cursor-pointer items-center gap-3 p-3"><input type="radio" name="accountant_id" value="{{ $accountant->id }}" :required="accountType === 'accountant'"><span><strong class="ui-title block">{{ $accountant->name }}</strong><small class="ui-text-muted">{{ $accountant->store?->name ?: 'متجر غير محدد' }}</small></span></label>@empty<div class="ui-alert ui-alert-warning sm:col-span-2">لا يوجد محاسب نشط يمكن اختياره.</div>@endforelse</div></div>
        <div><label class="ui-text-soft mb-1 block">تصنيف الطلب</label><select name="category" class="ui-input w-full" required><option value="general">طلب عام</option><option value="restore">استعادة محذوفات</option><option value="accounting">مراجعة حسابية</option><option value="inventory">مراجعة مخزون</option><option value="account">مشكلة حساب</option></select></div>
        <div><label class="ui-text-soft mb-1 block">الأولوية</label><select name="priority" class="ui-input w-full" required><option value="normal">عادية</option><option value="low">منخفضة</option><option value="high">مرتفعة</option><option value="urgent">عاجلة</option></select></div>
        <div class="md:col-span-2"><label class="ui-text-soft mb-1 block">عنوان الطلب</label><input name="subject" value="{{ old('subject') }}" class="ui-input w-full" required maxlength="150"></div>
        <div class="md:col-span-2"><label class="ui-text-soft mb-1 block">ما الذي تريد من الدعم تنفيذه؟</label><textarea name="description" class="ui-input w-full" rows="5" required maxlength="4000">{{ old('description') }}</textarea></div>
        <div class="md:col-span-2"><button class="ui-btn ui-btn-primary" type="submit">إرسال طلب الدعم</button></div>
    </form>
    <div class="space-y-3">
        @forelse($tickets as $ticket)
            <article class="ui-card p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><div class="ui-title font-bold">{{ $ticket->subject }}</div><div class="ui-text-muted">رقم التذكرة: {{ $ticket->reference }} — {{ $ticket->category_label }} — {{ $ticket->priority_label }}</div></div><div class="flex items-center gap-2"><span class="ui-badge ui-badge-info">{{ $ticket->status_label }}</span>@if($ticket->owner_unread_count)<span class="ui-badge ui-badge-warning">{{ $ticket->owner_unread_count }} جديد</span>@endif</div></div>
                <p class="ui-text-soft mt-3 whitespace-pre-line">{{ $ticket->description }}</p>@if(!$ticket->responded_at && $ticket->expires_at && in_array($ticket->status, \App\Models\SupportTicket::ACTIVE_STATUSES, true))<p class="ui-text-muted mt-2">يُلغى الطلب إن لم يصل رد قبل {{ $ticket->expires_at->format('Y-m-d H:i') }}.</p>@endif @if($ticket->cancel_reason)<div class="ui-alert ui-alert-warning mt-3">{{ $ticket->cancel_reason }}</div>@endif
                @if($ticket->support_response)<div class="ui-alert ui-alert-info mt-3"><strong>رد الدعم التقني:</strong> {{ $ticket->support_response }}</div>@endif
                <a href="{{ route('user.support-tickets.show', $ticket) }}" class="ui-btn ui-btn-secondary mt-3">فتح المحادثة والتفاصيل</a>
            </article>
        @empty
            <div class="ui-card p-8 text-center ui-text-muted">لا توجد طلبات دعم حتى الآن.</div>
        @endforelse
    </div>
    {{ $tickets->links() }}
</div>
@endsection
