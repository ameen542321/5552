<x-error-page
    page-title="419 - انتهت الجلسة"
    status-code="419"
    status-class="ui-status-warning"
    heading="انتهت الجلسة"
    message="انتهت الجلسة. يرجى تحديث الصفحة أو تسجيل الدخول من جديد.">
    <a href="{{ url()->current() }}" class="ui-btn ui-btn-primary w-full sm:w-auto">تحديث الصفحة</a>
    <button type="button" data-history-back class="ui-btn ui-btn-secondary w-full sm:w-auto">رجوع للخلف</button>
    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">تسجيل الدخول</a>
</x-error-page>
