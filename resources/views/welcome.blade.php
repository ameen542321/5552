<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carled</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="ui-page ui-public-page-shell">
    <x-ui.page-loader />

    <header class="ui-topbar sticky top-0 z-40">
        <div class="ui-public-header-inner">
            <a href="{{ url('/') }}" class="ui-public-brand-link">CARLED</a>
        </div>
    </header>

    <main class="ui-public-page-main">
    <section id="about" class="min-h-screen flex flex-col items-center justify-center text-center px-4 py-10 sm:px-6">

        <h1 class="mb-6 break-words text-5xl font-extrabold tracking-wide ui-brand-text sm:text-6xl">
            CARLED
        </h1>

        <p class="ui-text-soft text-xl max-w-2xl leading-relaxed mb-10">
            منصة متكاملة لإدارة المتاجر، المحاسبة، الرواتب، المصاريف، الاشتراكات،
            وإدارة الموظفين — كل ذلك في نظام واحد بسيط، سريع، واحترافي.
        </p>

        @if(auth('accountant')->check())
            <form method="POST" action="{{ route('accountant.logout') }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn-secondary px-10 py-3 text-lg shadow-lg">تسجيل الخروج</button>
            </form>
        @elseif(auth('web')->check())
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ui-btn ui-btn-secondary px-10 py-3 text-lg shadow-lg">تسجيل الخروج</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="ui-btn ui-btn-primary inline-flex items-center justify-center px-10 py-3 text-lg shadow-lg">تسجيل الدخول</a>
        @endif

        <div class="mt-16 animate-bounce ui-text-muted">
            <svg class="w-8 h-8 ui-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 9l-7 7-7-7"/>
            </svg>
        </div>

    </section>

    <section id="services" class="py-20 px-6 ui-surface-muted-bg ui-border-top">

        <h2 class="text-4xl font-bold text-center ui-brand-text mb-12">
            خدمات Carled
        </h2>

        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4 lg:gap-10">

            <div class="ui-card p-6 text-center ui-hover-info-border transition">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold mb-4">إدارة المتاجر</h3>
                <p class="ui-text-muted leading-relaxed">
                    إدارة كاملة للمتاجر والفروع مع صلاحيات دقيقة لكل مستخدم.
                </p>
            </div>

            <div class="ui-card p-6 text-center ui-hover-info-border transition">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2v6h6v-6c0-1.105-1.343-2-3-2z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold mb-4">المحاسبة والمصاريف</h3>
                <p class="ui-text-muted leading-relaxed">
                    تتبع المصاريف، الرواتب، السحوبات، والتقارير المالية بشكل احترافي.
                </p>
            </div>

            <div class="ui-card p-6 text-center ui-hover-info-border transition">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M5.121 17.804A4 4 0 0112 15a4 4 0 016.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold mb-4">إدارة الموظفين</h3>
                <p class="ui-text-muted leading-relaxed">
                    إدارة العمال، الرواتب، السجلات، السحوبات، والمهام اليومية بسهولة.
                </p>
            </div>

            <div class="ui-card p-6 text-center ui-hover-info-border transition">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto ui-status-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-semibold mb-4">الاشتراكات</h3>
                <p class="ui-text-muted leading-relaxed">
                    خطط اشتراك مرنة تناسب حجم متجرك مع إمكانية الترقية بسهولة.
                </p>
            </div>

        </div>

    </section>

    <section class="py-20 px-6">

        <h2 class="text-4xl font-bold text-center ui-brand-text mb-12">
            لماذا Carled؟
        </h2>

        <div class="max-w-4xl mx-auto text-center ui-text-soft text-lg leading-relaxed">
            Carled يجمع كل ما يحتاجه صاحب المتجر في منصة واحدة:
            إدارة، محاسبة، رواتب، مصاريف، اشتراكات، موظفين، تقارير، وإشعارات —
            بدون تعقيد، بدون إضافات غير ضرورية، وبدون تكلفة عالية.
        </div>

    </section>

    <section id="features" class="py-20 px-6 ui-surface-muted-bg ui-border-top">

        <h2 class="text-4xl font-bold text-center ui-brand-text mb-12">
            مميزات النظام
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 max-w-6xl mx-auto">

            <div class="ui-card p-6 text-center">
                <h3 class="text-xl font-semibold mb-3">سهولة الاستخدام</h3>
                <p class="ui-text-muted">واجهة بسيطة وسريعة بدون أي تعقيد.</p>
            </div>

            <div class="ui-card p-6 text-center">
                <h3 class="text-xl font-semibold mb-3">سرعة عالية</h3>
                <p class="ui-text-muted">أداء ممتاز حتى مع البيانات الكبيرة.</p>
            </div>

            <div class="ui-card p-6 text-center">
                <h3 class="text-xl font-semibold mb-3">تقارير دقيقة</h3>
                <p class="ui-text-muted">تحليل شامل لكل عملياتك المالية والإدارية.</p>
            </div>

        </div>

    </section>

    <section class="py-20 px-6">

        <h2 class="text-4xl font-bold text-center ui-brand-text mb-12">
            آراء العملاء
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 max-w-6xl mx-auto">

            <div class="ui-card p-6">
                <p class="ui-text-soft mb-4">
                    "بفضل استخدامي للبرنامج لم يعد  يكفيني عصبة من الرجال لحمل مفاتيح خزائني  ."
                </p>
                <p class="ui-brand-text font-semibold">—   قارون</p>
            </div>

            <div class="ui-card p-6">
                <p class="ui-text-soft mb-4">
                    " لا باس به فلقد ساعدنا في جمع الثروات والغنائم لنقاتل محمدا ومن صبا من قومة  ."
                </p>
                <p class="ui-brand-text font-semibold">— ابو جهل </p>
            </div>

            <div class="ui-card p-6">
                <p class="ui-text-soft mb-4">
                    "لقد استطعت ادارة قوافلي شتاء وصيفا الا القافلة التي اخذها محمدا وصحبة"
                </p>
                <p class="ui-brand-text font-semibold">—  الوليد ابن المغيرة</p>
            </div>

        </div>

    </section>

    </main>

    <footer id="contact" class="ui-public-footer" x-data="{ footerLinksOpen: false }" @keydown.escape.window="footerLinksOpen = false">

        <button type="button"
                class="ui-public-footer-brand-trigger"
                @click="footerLinksOpen = !footerLinksOpen"
                :aria-expanded="footerLinksOpen.toString()"
                aria-controls="public-footer-links">
            CARLED
            <span class="ui-text-caption" aria-hidden="true">▾</span>
        </button>

        <nav id="public-footer-links"
             x-cloak
             x-show="footerLinksOpen"
             x-transition.opacity.duration.150ms
             class="ui-public-footer-links-panel ui-text-caption font-bold"
             aria-label="روابط الفوتر">
            <a href="#about" @click="footerLinksOpen = false" class="ui-btn ui-btn-secondary px-3 py-2">من نحن</a>
            <a href="#services" @click="footerLinksOpen = false" class="ui-btn ui-btn-secondary px-3 py-2">الخدمات</a>
            <a id="terms" href="#terms" @click="footerLinksOpen = false" class="ui-btn ui-btn-secondary px-3 py-2">الشروط والأحكام</a>
            <a id="privacy" href="#privacy" @click="footerLinksOpen = false" class="ui-btn ui-btn-secondary px-3 py-2">سياسة الخصوصية</a>
            <a href="#contact" @click="footerLinksOpen = false" class="ui-btn ui-btn-secondary px-3 py-2">تواصل معنا</a>
        </nav>

        <p class="ui-text-muted text-sm">
            © {{ date('Y') }} Carled — جميع الحقوق محفوظة
        </p>

    </footer>

</body>
</html>
