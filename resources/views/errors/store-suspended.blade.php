<x-error-page
    page-title="المتجر موقوف"
    status-class="ui-status-danger"
    heading="عذرًا، تم إيقاف هذا المتجر"
    message="لا يمكنك الوصول إلى هذا المتجر حاليًا.">
    <a href="{{ url('/') }}" class="ui-btn ui-btn-primary w-full sm:w-auto">الصفحة الرئيسية</a>
    <button type="button" data-history-back class="ui-btn ui-btn-secondary w-full sm:w-auto">رجوع للخلف</button>
    <a href="{{ route('login') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">تسجيل الدخول</a>
</x-error-page>
