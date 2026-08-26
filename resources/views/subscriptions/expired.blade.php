@extends('dashboard.app')

@section('content')
<div class="min-h-screen flex items-center justify-center ui-page px-4">

    <div class="ui-card shadow-lg rounded-xl p-8 max-w-md w-full text-center">

        <div class="mb-6">
            <div class="mx-auto w-16 h-16 rounded-full ui-status-danger-bg flex items-center justify-center">
                <span class="ui-status-danger text-3xl">!</span>
            </div>
        </div>

        <h1 class="text-2xl font-semibold ui-title mb-3">
            انتهت مدة اشتراكك
        </h1>

        <p class="ui-text-soft text-sm leading-relaxed mb-6">
            لا يمكنك استخدام النظام في الوقت الحالي لأن اشتراكك قد انتهى.
            يرجى تجديد الاشتراك للمتابعة في استخدام خدمات المنصة.
        </p>

        <a href="{{ route('subscription.renew') }}"
           class="inline-block w-full py-2.5 ui-btn ui-btn-primary rounded-lg transition">
            تجديد الاشتراك الآن
        </a>

        <div class="mt-4">


             @auth
        <form id="logout-form" action="{{ route('logout') }}" method="POST">
            @csrf
            <button  type="submit">تسجيل خروج والعودة للصفحة الرئيسية</button>
        </form>
    @endauth
        </div>

    </div>

</div>
@endsection
