<x-error-page
    page-title="404 - الصفحة غير موجودة"
    status-code="404"
    status-class="ui-status-info"
    heading="الصفحة غير موجودة"
    message="عذرًا، الصفحة التي تبحث عنها غير موجودة.">
    <a href="{{ url('/') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">الصفحة الرئيسية</a>
    <button type="button" data-history-back class="ui-btn ui-btn-secondary w-full sm:w-auto">رجوع للخلف</button>
    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">تسجيل الدخول</a>
</x-error-page>
