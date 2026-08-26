{{-- إصلاح مطبق: زر الثيم يستخدم وحدة التنقل المشتركة بدل منطق Alpine المكرر. --}}
<nav class="ui-topbar sticky top-0 z-50 shadow-xl"
     data-realtime-notifications
     data-notification-channel="user.{{ $auth->id }}"
     x-data="{
        openMenu: false,
        openUser: false,
        openNotif: false,
        openStoreMenu: false,
        openStoresNav: false,
        init() {
            // إغلاق جميع القوائم عند التحميل
            this.openStoreMenu = false;
            this.openStoresNav = false;
            this.openMenu = false;
            this.openUser = false;
            this.openNotif = false;
        },

        toggleStoreMenu() {
            this.openMenu = false;
            this.openUser = false;
            this.openNotif = false;
            this.openStoresNav = false;
            this.openStoreMenu = !this.openStoreMenu;
        },

        closeAllMenus() {
            this.openStoreMenu = false;
            this.openStoresNav = false;
            this.openMenu = false;
            this.openUser = false;
            this.openNotif = false;
        }
     }"
     @keydown.escape.window="closeAllMenus()">

    <div class="mx-auto w-full max-w-full px-2 sm:px-6 md:px-8">
        <div class="flex justify-between h-16 items-center w-full">

            {{-- ===== الجهة اليسرى: الهمبرجر الرئيسي + الشعار + قائمة المتجر ===== --}}
            <div class="flex min-w-0 flex-1 items-center gap-2">
                {{-- زر الهمبرجر الرئيسي --}}
                <button @click="openMenu = !openMenu; openStoreMenu = false; openStoresNav = false; openUser = false; openNotif = false"
                        class="ui-theme-toggle">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>

                {{-- شعار CARLED --}}
                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2 group ml-2">
                    <div class="relative">
                        <div class="ui-logo-dot"></div>
                        <div class="ui-logo-ping"></div>
                    </div>
                    <span class="ui-brand-word">Car<span class="auth-logo-accent">led</span></span>
                </a>


                {{-- 🏬 تنقل سريع للمتاجر الفعالة في الصفحات العامة --}}
                @if(! $isInStore && $activeStores->isNotEmpty())
                    <div class="ui-nav-divider relative mr-2 shrink-0 pr-2">
                        <button @click="openStoresNav = !openStoresNav; openMenu = false; openStoreMenu = false; openUser = false; openNotif = false"
                                class="ui-nav-action"
                                :aria-expanded="openStoresNav.toString()">
                            <i class="fa-solid fa-store text-lg ui-nav-action-icon"></i>
                            <span class="inline text-sm font-bold">متاجري</span>
                        </button>

                        <div x-show="openStoresNav"
                             @click.outside="openStoresNav = false"
                             x-cloak
                             x-transition
                             class="ui-header-dropdown fixed inset-x-3 top-16 z-[60] mx-auto py-3 ui-dropdown-panel sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:mx-0">
                            <div class="ui-dropdown-header-block">
                                <p class="ui-title text-sm">تنقل سريع للمتاجر الفعالة</p>
                                <p class="ui-text-muted ui-text-caption mt-1">اختر المتجر ثم الصفحة المطلوبة مباشرة</p>
                            </div>
                            <div class="max-h-96 overflow-y-auto custom-scroll p-2 space-y-2" data-store-accordion>
                                @foreach($activeStores as $quickStore)
                                    <details class="ui-store-nav-card overflow-hidden" data-store-accordion-item>
                                        <summary class="ui-store-nav-summary">
                                            <span class="flex items-center gap-2 ui-title text-sm">
                                                <i class="fa-solid fa-store ui-nav-action-icon"></i>
                                                {{ $quickStore->name }}
                                            </span>
                                            <i class="fa-solid fa-chevron-down ui-text-muted ui-text-caption"></i>
                                        </summary>
                                        <div class="grid grid-cols-2 gap-2 p-3 ui-border-top">
                                            <a href="{{ route('user.stores.show', $quickStore->id) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">الرئيسية</a>
                                            <a href="{{ route('user.stores.daily', $quickStore->id) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">المبيعات</a>
                                            <a href="{{ route('user.stores.products.index', $quickStore->id) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">المنتجات</a>
                                            <a href="{{ route('user.stores.expenses.index', $quickStore->id) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">المصروف</a>
                                            <a href="{{ route('user.employees.index', ['store' => $quickStore->id]) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">الموظفين</a>
                                            <a href="{{ route('user.stores.reports.index', $quickStore->id) }}" class="ui-quick-link-card px-3 py-2 ui-text-caption font-bold">التقارير</a>
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif(! $isInStore)
                    <a href="{{ route('user.stores.index') }}" class="ui-nav-action mr-2">
                        <i class="fa-solid fa-store text-lg ui-nav-action-icon"></i>
                        <span class="inline text-sm font-bold">متاجري</span>
                    </a>
                @endif

                {{-- 🏬 قائمة المتجر - تظهر فقط داخل المتجر --}}
                @if($isInStore && $storeId)
                    <div class="ui-nav-divider relative mr-2 min-w-0 pr-2">
                        <button @click="toggleStoreMenu()"
                                class="ui-nav-action min-w-0 gap-1.5"
                                :aria-expanded="openStoreMenu.toString()">
                            <i class="fa-solid fa-bars-staggered text-xl"></i>
                            <span class="hidden sm:inline text-sm font-medium">المتجر</span>
                            <span class="ui-store-badge ui-text-caption block max-w-[5.5rem] overflow-hidden text-ellipsis whitespace-nowrap sm:max-w-48" title="{{ $storeName }}">{{ $storeName }}</span>
                        </button>

                        {{-- قائمة المتجر المنسدلة --}}
                        <div x-show="openStoreMenu"
                             @click.outside="openStoreMenu = false"
                             x-cloak
                             x-transition
                             class="ui-header-dropdown fixed inset-x-3 top-16 z-[60] mx-auto py-3 ui-dropdown-panel sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:mx-0">

                            {{-- مبدل المتاجر السريع يظهر أولاً لأن اسم المتجر الحالي واضح في رأس الصفحة نفسها. --}}
                            <div class="p-2 ui-border-bottom">
                                <label class="ui-text-muted ui-text-caption block px-2 mb-2">اختر متجر للذهاب إليه</label>
                                <select
                                    data-store-navigation-select
                                    class="ui-input text-sm">
                                    @foreach($activeStores as $store)
                                        <option value="{{ $storeSwitchUrls->get((string) $store->id) }}"
                                                {{ $store->id == $storeId ? 'selected' : '' }}>
                                            {{ $store->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- روابط المتجر --}}
                            <div class="p-2">
                                @foreach($storeMenuItems as $item)
                                    <a href="{{ $item['url'] }}"
                                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition
                                              {{ $item['active'] ? 'ui-store-menu-item-active' : 'ui-store-menu-item-rest' }}">
                                        <i class="fa-solid fa-{{ $item['icon'] }} w-5 text-center"></i>
                                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                                        @if($item['active'])
                                            <span class="ui-current-badge">الحالي</span>
                                        @endif
                                    </a>
                                @endforeach
                                <a href="{{ route('user.stores.reports.index', ['store' => $storeId]) }}"
                                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition
                                          {{ request()->routeIs('user.stores.reports.*') ? 'ui-store-menu-item-active' : 'ui-store-menu-item-rest' }}">
                                    <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                                    <span class="text-sm font-medium">التقارير</span>
                                    @if(request()->routeIs('user.stores.reports.*'))
                                        <span class="ui-current-badge">الحالي</span>
                                    @endif
                                </a>

                                <div class="ui-dropdown-separator"></div>

                                {{-- إعدادات المتجر --}}
                                <a href="{{ route('user.stores.edit', ['store' => $storeId, 'return_to' => 'show']) }}"
                                   class="ui-store-menu-item ui-store-menu-item-rest">
                                    <i class="fa-solid fa-gear w-5 text-center"></i>
                                    <span class="text-sm font-medium">إعدادات المتجر</span>
                                </a>


                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ===== الجهة اليمنى: الإشعارات والبروفايل ===== --}}
            <div class="ui-topbar-actions">
                {{-- زر تبديل النمط --}}
                <button
                    type="button"
                    data-theme-toggle
                    aria-label="تبديل النمط"
                    aria-pressed="false"
                    class="ui-topbar-action p-2">
                    <i data-theme-icon="light" class="fa-solid fa-sun" aria-hidden="true"></i>
                    <i data-theme-icon="dark" class="fa-solid fa-moon" aria-hidden="true"></i>
                </button>

                {{-- الإشعارات --}}
                <div class="relative">
                    <button @click="openNotif = !openNotif; openUser = false; openMenu = false; openStoreMenu = false; openStoresNav = false"
                            class="ui-topbar-action relative">
                        <i class="fa-regular fa-bell text-xl"></i>
                        <span data-notif-badge
                              class="ui-notification-count {{ $unreadCount > 0 ? '' : 'hidden' }}">
                            {{ $unreadCount }}
                        </span>
                    </button>

                    <div x-show="openNotif"
                         @click.outside="openNotif = false"
                         x-cloak
                         x-transition
                         class="ui-header-dropdown absolute left-0 mt-3 ui-dropdown-panel py-3 z-50">

                        <div class="ui-dropdown-header">
                            <h4 class="ui-title text-sm">التنبيهات</h4>
                            <span class="ui-badge ui-badge-info">جديد</span>
                        </div>

                        <div data-notif-list class="max-h-72 overflow-y-auto custom-scroll">
                            @forelse($latestNotifications as $n)
                                <a href="{{ route('user.notifications.show', $n->id) }}"
                                   class="ui-notification-item {{ $n->isReadBy($auth->id) ? 'opacity-60' : 'ui-notification-item-unread' }}">
                                    <div class="flex items-start gap-3">
                                        <div class="ui-notification-icon">
                                            <i class="fa-solid fa-bell"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="ui-text-caption line-clamp-1">{{ $n->title }}</div>
                                            <div class="ui-notification-body line-clamp-2">{{ $n->message }}</div>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div data-notification-empty-state class="ui-text-muted px-4 py-8 text-center ui-text-caption italic">لا توجد إشعارات حالياً</div>
                            @endforelse
                        </div>

                        <div class="mt-2 pt-2 px-3">
                            <a href="{{ route('user.notifications.index') }}" class="ui-dropdown-footer-link">عرض كل الإشعارات</a>
                        </div>
                    </div>
                </div>

                {{-- البروفايل --}}
                <div class="relative hidden sm:block">
                    <button @click="openUser = !openUser; openNotif = false; openMenu = false; openStoreMenu = false; openStoresNav = false"
                            class="ui-account-menu-button group">
                        <div class="text-right hidden sm:block">
                            <p class="ui-account-name">{{ $auth->name }}</p>
                            <div class="flex items-center justify-end gap-1.5 mt-0.5">
                                <span class="ui-plan-badge">{{ $plan->name ?? 'Basic' }}</span>
                                <span class="ui-text-muted ui-text-caption font-medium italic">{{ $auth->subscription_end_at ? \Carbon\Carbon::parse($auth->subscription_end_at)->format('Y-m-d') : '∞' }}</span>
                            </div>
                        </div>
                        <div class="relative">
                            <span class="ui-avatar" aria-label="{{ $auth->name }}">
                                {{ mb_substr($auth->name, 0, 1) }}
                            </span>
                            <div class="ui-online-dot"></div>
                        </div>
                    </button>

                    <div x-show="openUser"
                         @click.outside="openUser = false"
                         x-cloak
                         x-transition
                         class="ui-header-dropdown absolute left-0 mt-3 ui-dropdown-panel py-2 z-50">

                        <div class="ui-account-panel-section">
                            {{-- تاريخ انتهاء الاشتراك --}}
                            <div>
                                <p class="ui-text-muted ui-text-caption uppercase font-bold mb-1 text-right">صلاحية الاشتراك</p>
                                <div class="flex items-center justify-end gap-2 ui-text-soft">
                                    @if($auth->subscription_end_at)
                                        <span class="ui-text-caption font-bold {{ \Carbon\Carbon::parse($auth->subscription_end_at)->isFuture() ? 'ui-status-positive' : 'ui-status-danger' }}">
                                            {{ \Carbon\Carbon::parse($auth->subscription_end_at)->translatedFormat('d M Y') }}
                                        </span>
                                    @else
                                        <span class="ui-text-caption font-bold ui-status-positive">مفتوح (دائم)</span>
                                    @endif
                                    <i class="fa-solid fa-calendar-day ui-text-caption ui-text-muted"></i>
                                </div>
                            </div>
                            <details class="mt-3 pt-3 ui-border-top">
                                <summary class="list-none cursor-pointer flex items-center justify-between ui-text-soft">
                                    <span class="text-sm font-black">لوحة المعلومات</span>
                                    <i class="fa-solid fa-chevron-down ui-text-caption ui-text-muted"></i>
                                </summary>
                                <div class="mt-3">
                                    <p class="ui-text-muted ui-text-caption uppercase font-bold mb-1 text-right">استهلاك المتاجر</p>
                                    <progress class="ui-progress mb-2" value="{{ $currentStores }}" max="{{ max(1, $plan->allowed_stores ?? 1) }}"></progress>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3 text-center">
                                    <div class="ui-mini-stat">
                                        <p class="ui-text-muted ui-text-caption">المتاجر</p>
                                        <p class="text-sm ui-title font-black">{{ $currentStores }}/{{ $allowedStores }}</p>
                                    </div>
                                    <div class="ui-mini-stat">
                                        <p class="ui-text-muted ui-text-caption">المحاسبين</p>
                                        <p class="text-sm ui-title font-black">{{ $currentAccountants }}/{{ $plan->allowed_accountants ?? 0 }}</p>
                                    </div>
                                    <div class="ui-mini-stat">
                                        <p class="ui-text-muted ui-text-caption">الموظفين</p>
                                        <p class="text-sm ui-title font-black">{{ $auth->employees()->count() }}</p>
                                    </div>
                                </div>
                            </details>

                        </div>

                        <div class="ui-dropdown-separator"></div>

                        <a href="{{ route('user.support-tickets.index') }}" class="ui-support-ticket-link">
                            <span class="ui-support-ticket-icon"><i class="fa-solid fa-headset" aria-hidden="true"></i></span>
                            <span>
                                <strong class="block">طلبات الدعم التقني</strong>
                                <small class="block">متابعة الطلبات والتواصل مع الدعم</small>
                            </span>
                            <i class="fa-solid fa-chevron-left ui-text-muted" aria-hidden="true"></i>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="ui-dropdown-danger-action font-bold">
                                <i class="fa-solid fa-power-off w-4 text-center"></i><span>تسجيل الخروج</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== القائمة الرئيسية المنسدلة (تظهر عند الضغط على زر الهمبرجر) ===== --}}
    <div x-show="openMenu"
         @click.outside="openMenu = false"
         x-cloak
         x-transition
         class="ui-mobile-menu px-4 py-5 shadow-inner">

        <div class="mx-auto max-w-7xl space-y-5">
        <div class="ui-card flex items-center justify-between gap-3 p-3 sm:hidden">
            <div class="flex min-w-0 items-center gap-3">
                <span class="ui-avatar shrink-0" aria-hidden="true">{{ mb_substr($auth->name, 0, 1) }}</span>
                <div class="min-w-0">
                    <p class="ui-title truncate text-sm font-black">{{ $auth->name }}</p>
                    <p class="ui-text-muted ui-text-caption truncate">{{ $plan->name ?? 'Basic' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <button type="submit" class="ui-btn ui-btn-danger h-10 w-10 p-0" aria-label="تسجيل الخروج">
                    <i class="fa-solid fa-power-off"></i>
                </button>
            </form>
        </div>
        {{-- روابط التنقل أسفل البطاقات --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <a href="{{ route('user.stores.index') }}"
               class="ui-quick-link-card group flex flex-col items-center p-3 rounded-xl transition-all hover:-translate-y-0.5">
                <i class="fa-solid fa-store ui-nav-action-icon mb-1"></i>
                <span class="ui-text-caption font-bold">متاجري</span>
            </a>

            <a href="{{ route('user.employees.index') }}"
               class="ui-quick-link-card group flex flex-col items-center p-3 rounded-xl transition-all hover:-translate-y-0.5">
                <i class="fa-solid fa-users ui-nav-action-icon mb-1"></i>
                <span class="ui-text-caption font-bold">الموظفين</span>
            </a>

            {{-- يبقى رابط التنبيهات ضمن ترتيب قائمة الهمبرجر كما طلب المالك. --}}
            <a href="{{ route('user.administrative-alerts') }}"
               class="ui-quick-link-card group flex flex-col items-center p-3 rounded-xl transition-all hover:-translate-y-0.5">
                <i class="fa-solid fa-triangle-exclamation ui-nav-action-icon mb-1"></i>
                <span class="ui-text-caption font-bold">تنبيهات إدارية</span>
            </a>

            <a href="{{ route('user.notifications.send') }}"
               class="ui-quick-link-card group flex flex-col items-center p-3 rounded-xl transition-all hover:-translate-y-0.5">
                <i class="fa-solid fa-paper-plane ui-nav-action-icon mb-1"></i>
                <span class="ui-text-caption font-bold">إرسال إشعار</span>
            </a>
        </div>


        {{-- إذا كنا داخل متجر، نضيف رابط سريع للمتجر --}}
        @if($isInStore)
        <div class="pt-4 ui-border-top">
            <div class="flex items-center justify-between">
                <span class="ui-text-muted ui-text-caption">أنت الآن في:</span>
                <a href="{{ route('user.stores.show', $storeId) }}" class="flex items-center gap-2 ui-brand-link transition">
                    <i class="fa-solid fa-store"></i>
                    <span class="text-sm font-bold">{{ $storeName ?? 'المتجر' }}</span>
                </a>
            </div>
        </div>
        @endif
        </div>
    </div>
</nav>
