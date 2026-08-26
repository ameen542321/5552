<x-error-page
    page-title="لا تملك صلاحية الوصول"
    status-class="ui-status-danger"
    icon="🚫"
    max-width="max-w-lg"
    heading="لا تملك صلاحية الوصول"
    message="عذرًا، لا يمكنك الوصول إلى هذه الصفحة.">
    @guest
        <a href="/" class="ui-btn ui-btn-primary w-full sm:w-auto">العودة للصفحة الرئيسية</a>
    @endguest

    @auth
        <form action="{{ route('logout') }}" method="POST" class="w-full sm:w-auto">
            @csrf
            <button type="submit" class="ui-btn ui-btn-secondary w-full">تسجيل خروج والعودة للصفحة الرئيسية</button>
        </form>
    @endauth
</x-error-page>
