<x-error-page
    page-title="خطأ 500 - خطأ في الخادم"
    status-code="500"
    status-class="ui-status-danger"
    heading="خطأ في الخادم"
    message="حدث خطأ غير متوقع في الخادم. نعمل على إصلاحه.">
    <a href="{{ url('/') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">الصفحة الرئيسية</a>
    <button type="button" data-history-back class="ui-btn ui-btn-secondary w-full sm:w-auto">رجوع للخلف</button>
    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">تسجيل الدخول</a>
</x-error-page>
