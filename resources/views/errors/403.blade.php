<x-error-page
    page-title="403 - غير مسموح بالوصول"
    status-code="403"
    status-class="ui-status-warning"
    heading="غير مسموح بالوصول"
    message="عذرًا، لا تملك صلاحية الوصول إلى هذه الصفحة.">
    <a href="{{ url('/') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">الصفحة الرئيسية</a>
    <button type="button" data-history-back class="ui-btn ui-btn-secondary w-full sm:w-auto">رجوع للخلف</button>
    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">تسجيل الدخول</a>
</x-error-page>
