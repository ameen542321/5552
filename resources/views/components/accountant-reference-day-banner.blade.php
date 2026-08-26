@props(['date'])

@php
    $referenceDayName = \Carbon\Carbon::parse($date)->locale('ar')->translatedFormat('l');
@endphp

<div class="mb-4 inline-flex flex-wrap items-center justify-center gap-2 rounded-xl border ui-border ui-status-success-bg px-3 py-2 ui-text-caption font-bold ui-status-success">
    <span aria-label="وضع مراجعة الشفت مفعل" class="inline-flex items-center gap-1 rounded-full ui-status-success-bg ui-status-success px-2 py-1 font-black">
        <i class="fa-solid fa-check-circle" aria-hidden="true"></i> مفعل
    </span>
    <span class="ui-title font-bold" aria-label="اسم يوم العمل المرجع">{{ $referenceDayName }}</span>
    <span aria-label="أي عملية جديدة ستسجل على تاريخ العمل المرجع" class="font-mono ui-title">{{ $date }}</span>
    <form method="POST" action="{{ route('accountant.shift-gaps.clear') }}" class="inline">
        @csrf
        @method('DELETE')
        <button type="submit" aria-label="تأجيل الطلب والعودة لإدخال عمليات الشفت الحالي" class="ui-btn ui-btn-secondary rounded-lg px-2 py-1 ui-text-caption font-bold">
            تأجيل
        </button>
    </form>
</div>
