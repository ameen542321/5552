{{-- إصلاح مطبق: زر الثيم يستخدم data hook مشتركًا دون JavaScript مضمن. --}}
<nav class="ui-topbar sticky top-0 z-50"
     data-realtime-notifications
     data-notification-channel="user.{{ $auth->id }}"
     x-data="{ openMenu: false, openUser: false, openNotif: false }">

    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">

            <div class="flex items-center gap-3 sm:gap-6">
                {{-- زر القائمة للجوال --}}
               <button @click="openMenu = !openMenu" type="button" aria-label="فتح القائمة" class="ui-theme-toggle group">
                    {{-- أيقونة SVG كحل ثابت حتى لو Font Awesome لم يُحمَّل --}}
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                    {{-- توليب لزر القائمة --}}
                    <span class="ui-tooltip-popover">
                        القائمة الرئيسية
                    </span>
                </button>

                {{-- شعار CARLED: تم وضعه هنا ليكون أول ما تقع عليه العين في القراءة العربية --}}
                <a href="{{ route('accountant.dashboard') }}" class="flex items-center gap-2 group">
                    <div class="relative">
                        <div class="ui-logo-dot"></div>
                        <div class="ui-logo-ping"></div>
                    </div>
                    <span class="ui-brand-word">Car<span class="auth-logo-accent">led</span></span>
                </a>

            </div>
            {{-- زر تبديل النمط - يوضع بجانب الإشعارات --}}
            <button
                type="button"
                data-theme-toggle
                aria-label="تبديل النمط"
                aria-pressed="false"
                class="ui-theme-toggle group"
            >
                {{-- أيقونة SVG ثابتة لتبديل النمط بدون الاعتماد على Font Awesome --}}
                <svg data-theme-icon="light" class="w-5 h-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <svg data-theme-icon="dark" class="w-5 h-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 1 0 9.8 9.8Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </svg>
                {{-- توليب لزر تبديل النمط --}}
                <span class="ui-tooltip-popover">
                    تبديل الوضع
                </span>
            </button>
            <div class="ui-topbar-actions">


                {{-- الإشعارات --}}
                <div class="relative">
                    <button
                        @click="openNotif = !openNotif; openUser = false"
                        type="button" aria-label="الإشعارات"
                        class="ui-topbar-action p-2 relative group"
                    >
                        <i class="fa-regular fa-bell text-xl"></i>
                        <span data-notif-badge class="ui-notification-count {{ $unreadCount > 0 ? '' : 'hidden' }}">
                            {{ $unreadCount }}
                        </span>
                        {{-- توليب لزر الإشعارات --}}
                        <span class="ui-tooltip-popover">
                            الإشعارات
                        </span>
                    </button>

                    {{-- قائمة الإشعارات المنسدلة --}}
                    <div
                        x-show="openNotif"
                        @click.outside="openNotif = false"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        class="ui-header-dropdown absolute left-0 mt-3 ui-dropdown-panel z-50"
                    >
                        <div class="ui-dropdown-header">
                            <h4 class="ui-title">الإشعارات</h4>
                            <span class="ui-badge ui-badge-info">{{ $unreadCount }} جديدة</span>
                        </div>

                        <div data-notif-list class="max-h-[400px] overflow-y-auto custom-scroll">
                            @forelse($latestNotifications as $n)
                                <a href="{{ route('accountant.notifications.show', $n->id) }}"
                                   class="ui-notification-item {{ $n->isReadBy($auth->id) ? 'opacity-60' : 'ui-notification-item-unread' }}">
                                    <div class="ui-notification-icon">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="ui-notification-title">{{ $n->title }}</p>
                                        <p class="ui-notification-body line-clamp-2">{{ $n->message }}</p>
                                        <span class="ui-notification-time">{{ $n->created_at->diffForHumans() }}</span>
                                    </div>
                                </a>
                            @empty
                                <div data-notification-empty-state class="p-10 text-center">
                                    <i class="fa-solid fa-bell-slash ui-empty-icon"></i>
                                    <p class="ui-text-muted text-sm">لا توجد إشعارات حالياً</p>
                                </div>
                            @endforelse
                        </div>

                        <a href="{{ route('accountant.notifications.index') }}" class="ui-dropdown-footer-link">
                            عرض الكل
                        </a>
                    </div>
                </div>

                {{-- بروفايل المستخدم --}}
                <div class="relative">
                    <button type="button" aria-label="قائمة الحساب" @click="openUser = !openUser; openNotif = false" class="ui-account-menu-button group relative">
                       <div class="text-right hidden sm:block">
    <p class="ui-account-name">{{ $auth->name }}</p>
    {{-- إظهار اسم المتجر المرتبط --}}
    <p class="ui-account-store">
        <i class="fa-solid fa-store ui-account-store-icon"></i>
        {{ $auth->store->name ?? 'محاسب النظام' }}
    </p>
</div>
                        <span class="ui-avatar" aria-hidden="true">{{ mb_substr($auth->name, 0, 1) }}</span>
                        {{-- توليب لزر البروفايل --}}
                        <span class="ui-tooltip-popover">
                            حسابي
                        </span>
                    </button>

                    <div x-show="openUser" @click.outside="openUser = false" x-cloak x-transition class="absolute left-0 mt-3 w-52 ui-dropdown-panel py-2 z-50">

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="ui-dropdown-danger-action">
                                <i class="fa-solid fa-power-off"></i> تسجيل الخروج
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- قائمة الجوال --}}
   <div x-show="openMenu" @click.outside="openMenu = false" x-cloak x-transition class="ui-mobile-menu px-4 py-6 space-y-4 fixed top-16 inset-x-0 z-[70]">
    {{-- القائمة الرئيسية للمحاسب --}}
   {{-- بطاقتان في كل سطر كما كان ترتيب قائمة المحاسب المعتمد. --}}
   <div class="grid grid-cols-2 gap-2">

    <div class="contents" x-data="{ openAdministrativeTasks: false }">
        <button type="button"
                @click="openAdministrativeTasks = !openAdministrativeTasks"
                :aria-expanded="openAdministrativeTasks.toString()"
                aria-controls="accountant-administrative-tasks"
                class="ui-mobile-action-card group relative">
            <i class="fa-solid fa-list-check ui-mobile-action-icon" aria-hidden="true"></i>
            <span class="ui-text-caption font-bold uppercase">مهام إدارية</span>
            <span class="ui-tooltip-popover ui-tooltip-popover-top">فتح النقل المخزني وطلبيات التوريد ومهام الجرد</span>
        </button>

        <div id="accountant-administrative-tasks"
             x-show="openAdministrativeTasks"
             x-cloak
             x-transition
             class="col-span-2 grid grid-cols-3 gap-2 ui-card p-3">
            <a href="{{ route('accountant.transfers.index') }}" class="ui-mobile-action-card" aria-label="فتح النقل المخزني">
                <i class="fa-solid fa-right-left ui-mobile-action-icon" aria-hidden="true"></i>
                <span class="ui-text-caption font-bold">النقل المخزني</span>
            </a>
            <a href="{{ route('accountant.purchase-orders.index') }}" class="ui-mobile-action-card" aria-label="فتح طلبيات التوريد">
                <i class="fa-solid fa-clipboard-list ui-mobile-action-icon" aria-hidden="true"></i>
                <span class="ui-text-caption font-bold">طلبيات التوريد</span>
            </a>
            <a href="{{ route('accountant.inventory-counts.index') }}" class="ui-mobile-action-card" aria-label="فتح مهام الجرد">
                <i class="fa-solid fa-clipboard-check ui-mobile-action-icon" aria-hidden="true"></i>
                <span class="ui-text-caption font-bold">مهام الجرد</span>
            </a>
        </div>
    </div>

    {{-- ⭐ البيع السريع --}}
    <a href="{{ route('accountant.quick-sale.index') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-bolt ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">بيع سريع</span>
        {{-- توليب للبيع السريع --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            بيع منتج بسرعة
        </span>
    </a>

    {{-- يحافظ هذا الرابط على موضعه وترتيبه؛ التعديل نصي فقط. --}}
    <a href="{{ route('accountant.internal-use.create') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-box-open ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">إضافة استهلاك</span>
        {{-- توضيح نطاق إضافة الاستهلاك --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            تسجيل مواد استخدمها المحاسب من مخزون المتجر
        </span>
    </a>

    {{-- ⭐ إضافة مصروف --}}
    <a href="{{ route('accountant.pos.expense.page') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-money-bill-transfer ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">إضافة مصروف</span>
        {{-- توليب لإضافة مصروف --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            تسجيل مصروف جديد
        </span>
    </a>

    {{-- ⭐ سحب نقدي --}}
    <a href="{{ route('accountant.pos.withdrawal.page') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-hand-holding-dollar ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">سحب نقدي</span>
        {{-- توليب للسحب النقدي --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            سحب من الصندوق
        </span>
    </a>

    {{-- ⭐ تسجيل غياب --}}
    <a href="{{ route('accountant.pos.absence.page') }}" class="ui-mobile-action-card group relative">
        <div class="ui-mobile-action-icon-wrap">
            <i class="fa-solid fa-user-xmark text-lg"></i>
        </div>
        <p class="ui-mobile-action-text">تسجيل الغياب</p>
        {{-- توليب لتسجيل الغياب --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            تسجيل غياب الموظفين
        </span>
    </a>

    {{-- ⭐ إضافة / تحصيل مديونية --}}
    <a href="{{ route('accountant.pos.debt.page') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-money-bill-transfer ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">إضافة / تحصيل مديونية</span>
        {{-- توليب لإضافة أو تحصيل مديونية --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            إضافة مديونية أو تحصيلها
        </span>
    </a>

    {{-- ⭐ بحث المنتجات --}}
    <a href="{{ route('accountant.pos.searchProduct') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-boxes-stacked ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">بحث المنتجات</span>
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            البحث في المخزون
        </span>
    </a>


    {{-- ⭐ تحصيل --}}
    <a href="{{ route('accountant.pos.collection.page') }}" class="ui-mobile-action-card group relative">
        <div class="ui-mobile-action-icon-wrap">
            <i class="fa-solid fa-money-check-dollar text-lg"></i>
        </div>
        <p class="ui-mobile-action-text">تحصيل الأجل</p>
        {{-- توليب للتحصيل --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            تحصيل دفعات من العملاء
        </span>
    </a>

    {{-- ⭐ الفواتير --}}
    <a href="{{ route('accountant.invoices.index') }}"
       class="ui-mobile-action-card group relative">
        <i class="fa-solid fa-file-invoice ui-mobile-action-icon"></i>
        <span class="ui-text-caption font-bold uppercase">الفواتير</span>
        {{-- توليب للفواتير --}}
        <span class="ui-tooltip-popover ui-tooltip-popover-top">
            عرض كل الفواتير
        </span>
    </a>
</div>

</div>

</nav>
