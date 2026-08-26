@extends('dashboard.app')

@section('content')
@section('title', 'تفاصيل البلاغ الأمني')
@php
    $statusLabels = ['new' => 'جديد', 'investigating' => 'قيد التحقيق', 'contained' => 'تم الاحتواء', 'resolved' => 'تم الحل والتحقق', 'false_positive' => 'إنذار كاذب'];
    $actionLabels = ['acknowledge' => 'استلام البلاغ', 'assign' => 'تعيين مسؤول', 'add_note' => 'إضافة ملاحظة', 'contain' => 'اعتماد الاحتواء', 'block_source' => 'تقييد المصدر مؤقتًا', 'verify_resolve' => 'تحقق وإغلاق', 'false_positive' => 'تعليم كإنذار كاذب', 'release_source' => 'رفع تقييد المصدر', 'require_password_reset' => 'فرض إعادة تعيين كلمة المرور', 'cancel_password_reset' => 'إلغاء فرض إعادة التعيين', 'reopen' => 'إعادة فتح البلاغ'];
@endphp

<div class="security-command-header">
    <div>
        <a class="ui-text-soft" href="{{ route('admin.security.index') }}">العودة إلى مركز القيادة</a>
        <h1 class="ui-title text-2xl font-bold mt-2">{{ $event->title }}</h1>
        <p class="ui-text-muted mt-1">بلاغ #{{ $event->id }} · {{ $event->event_code }}</p>
    </div>
    <span role="status" class="ui-badge {{ in_array($event->severity, ['high', 'critical'], true) ? 'ui-badge-danger' : 'ui-badge-warning' }}">{{ $statusLabels[$event->status] ?? $event->status }}</span>
</div>

@if($errors->any())
    <div class="ui-alert ui-alert-danger mb-6" role="alert"><div class="ui-alert-body"><strong>تعذر تنفيذ الأمر.</strong><ul class="mt-2 list-disc pr-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif

<div class="security-detail-grid">
    <div class="xl:col-span-2 space-y-6">
        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">التقرير الميداني</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><dt class="ui-text-muted">التصنيف</dt><dd class="ui-text-soft mt-1">{{ $event->category }} / {{ $event->severity }} (ثقة {{ $event->confidence }}%)</dd></div>
                <div><dt class="ui-text-muted">عدد مرات الرصد</dt><dd class="ui-title mt-1">{{ number_format($event->occurrences) }}</dd></div>
                <div><dt class="ui-text-muted">المصدر المنقح</dt><dd class="ui-text-soft mt-1">{{ $event->masked_source_ip ?: 'غير متاح' }}</dd></div>
                <div><dt class="ui-text-muted">المسار</dt><dd class="ui-text-soft mt-1">{{ $event->http_method }} {{ $event->route ?: 'غير متاح' }}</dd></div>
                <div><dt class="ui-text-muted">أول رصد</dt><dd class="ui-text-soft mt-1">{{ $event->first_seen_at }}</dd></div>
                <div><dt class="ui-text-muted">آخر رصد</dt><dd class="ui-text-soft mt-1">{{ $event->last_seen_at }}</dd></div>
            </dl>
            @if($event->description)<div class="ui-alert ui-alert-info mt-5"><span class="ui-alert-body">{{ $event->description }}</span></div>@endif
        </section>

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">الأدلة المنقحة</h2>
            <div class="space-y-2">
                @forelse($event->evidence ?? [] as $key => $value)
                    <div class="ui-list-row security-evidence-row"><span class="ui-text-muted">{{ $key }}</span><span class="ui-text-soft break-words">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</span></div>
                @empty
                    <p class="ui-text-muted">لا توجد أدلة إضافية محفوظة.</p>
                @endforelse
            </div>
        </section>

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold">{{ $event->playbook['title'] ?? 'خطة الاستجابة' }}</h2>
            <ol class="mt-4 space-y-3 list-decimal pr-5 ui-text-soft">
                @foreach($event->playbook['steps'] ?? [] as $step)<li>{{ $step }}</li>@endforeach
            </ol>
            <div class="ui-alert ui-alert-info mt-5"><span class="ui-alert-body"><strong>شرط التحقق قبل الإغلاق:</strong> {{ $event->playbook['verification'] ?? 'وثق توقف السبب وعدم استمرار الأثر.' }}</span></div>
        </section>

        @if($event->verified_at)
            <section class="ui-card p-6">
                <h2 class="ui-title text-xl font-semibold mb-3">نتيجة التحقق</h2>
                <p class="ui-text-soft">{{ $event->verification_note }}</p>
                <p class="ui-text-muted mt-2">تحقق بواسطة {{ $event->verifier?->name ?: 'مدير النظام' }} في {{ $event->verified_at }}</p>
            </section>
        @endif

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-4">سجل الأوامر</h2>
            <div class="space-y-3">
                @forelse($event->activities->sortByDesc('created_at') as $activity)
                    <div class="ui-list-row"><div><strong class="ui-title">{{ $actionLabels[$activity->action] ?? $activity->action }}</strong><p class="ui-text-soft mt-1">{{ $activity->note ?: 'دون ملاحظة' }}</p></div><span class="ui-text-muted text-sm">{{ $activity->user?->name ?: 'النظام' }} · {{ $activity->created_at->diffForHumans() }}</span></div>
                @empty
                    <p class="ui-text-muted">لم يصدر أي أمر على هذا البلاغ بعد.</p>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="space-y-5 h-fit" aria-label="إدارة البلاغ">
        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-2">مسؤول البلاغ</h2>
            <p class="ui-text-soft mb-4">المسؤول الحالي: {{ $event->assignee?->name ?: 'لم يُعيّن بعد' }}</p>
            <form method="POST" action="{{ route('admin.security.action', $event) }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="assign">
                <label class="block"><span class="ui-field-label">تعيين إلى</span><select class="ui-input w-full mt-1" name="assigned_to" required><option value="">اختر مديرًا</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected((int) $event->assigned_to === $admin->id)>{{ $admin->name }}</option>@endforeach</select></label>
                <button class="ui-btn ui-btn-secondary w-full justify-center" type="submit"><i class="fa-solid fa-user-shield" aria-hidden="true"></i>حفظ المسؤول</button>
            </form>
        </section>

        <section class="ui-card p-6">
            <h2 class="ui-title text-xl font-semibold mb-2">ملاحظة التحقيق</h2>
            <form method="POST" action="{{ route('admin.security.action', $event) }}" class="space-y-3">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="add_note">
                <label class="block"><span class="ui-field-label">الملاحظة</span><textarea class="ui-input w-full mt-1" name="note" rows="4" minlength="5" maxlength="1000" required placeholder="دوّن ما فحصته أو ما يحتاج متابعة."></textarea></label>
                <button class="ui-btn ui-btn-secondary w-full justify-center" type="submit"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i>إضافة للسجل</button>
            </form>
        </section>

        <section class="ui-card p-6">
        <h2 class="ui-title text-xl font-semibold mb-2">إصدار أمر استجابة</h2>
        <p class="ui-text-soft mb-4">كل أمر يسجل باسمك وعنوان المصدر. اذكر السبب قبل الاحتواء أو الإغلاق.</p>
        <form method="POST" action="{{ route('admin.security.action', $event) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <label class="block"><span class="ui-field-label">الأمر</span><select class="ui-input w-full mt-1" name="action" required>
                @if($event->status === 'new')<option value="acknowledge">استلام البلاغ</option>@endif
                @if(in_array($event->status, ['new', 'investigating'], true))<option value="contain">اعتماد الاحتواء</option><option value="block_source">تقييد المصدر مؤقتًا</option><option value="false_positive">تعليم كإنذار كاذب</option>@endif
                @if(($event->target_type === \App\Models\User::class || $event->actor_type === \App\Models\User::class) && $event->response_action !== 'require_password_reset')<option value="require_password_reset">فرض إعادة تعيين كلمة المرور</option>@endif
                @if($event->status === 'contained')<option value="verify_resolve">تحقق وإغلاق البلاغ</option>@endif
                @if($event->response_action === 'block_source')<option value="release_source">رفع تقييد المصدر</option>@endif
                @if($event->response_action === 'require_password_reset')<option value="cancel_password_reset">إلغاء فرض إعادة التعيين</option>@endif
                @if(in_array($event->status, ['resolved', 'false_positive'], true))<option value="reopen">إعادة فتح البلاغ</option>@endif
            </select></label>
            <label class="block"><span class="ui-field-label">سبب الأمر أو نتيجة التحقق</span><textarea class="ui-input w-full mt-1" name="note" rows="5" minlength="5" maxlength="1000" placeholder="اكتب سببًا واضحًا؛ مطلوب للأوامر الحساسة."></textarea></label>
            <button class="ui-btn ui-btn-primary w-full justify-center" type="submit" data-ui-confirm="سيتم تنفيذ أمر الاستجابة وتسجيله باسمك. تأكد من كتابة سبب ونتيجة واضحة." data-ui-confirm-title="تنفيذ أمر أمني؟">تنفيذ الأمر وتوثيقه</button>
        </form>
        </section>
    </aside>
</div>
@endsection
