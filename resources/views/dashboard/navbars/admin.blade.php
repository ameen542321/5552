{{-- إصلاح مطبق: إضافة المستخدم تفتح المودال الفعلي بدل مسار إنشاء غير موجود. --}}
<nav class="ui-topbar"
     data-realtime-notifications
     data-notification-channel="user.{{ $auth->id }}"
     data-security-session-monitor
     data-security-check-url="{{ route('admin.security.maintenance.check') }}"
     data-security-check-interval="900000"
     x-data="{ openMenu: false, openUser: false, openNotif: false }">

    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">

            {{-- يسار --}}
            <div class="flex items-center gap-4">

                {{-- زر القائمة للجوال --}}
                <button @click="openMenu = !openMenu" class="ui-theme-toggle lg:hidden">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>

                {{-- الشعار --}}
                <div class="flex items-center gap-2">
                    <div class="ui-logo-dot"></div>
                    <span class="ui-brand-word">Carled</span>
                </div>

            </div>

            {{-- يمين --}}
            <div class="ui-topbar-actions">

                {{-- وصول مباشر وواضح لمركز الأمن على الشاشات المتوسطة والكبيرة. --}}
                <a href="{{ route('admin.security.index') }}"
                   class="ui-topbar-action group relative inline-flex items-center gap-2 px-3 py-2 {{ request()->routeIs('admin.security.*') ? 'ui-sidebar-link-active' : '' }}"
                   aria-label="فتح مركز القيادة الأمنية">
                    <i class="fa-solid fa-shield-halved text-xl" aria-hidden="true"></i>
                    <span class="hidden md:inline font-bold">مركز الأمن</span>
                    <span class="ui-tooltip-popover md:hidden">مركز الأمن</span>
                </a>

                {{-- الإشعارات --}}
                <div class="relative">
    {{-- زر الجرس --}}
    <button
        @click="openNotif = !openNotif; openUser = false"
        class="ui-topbar-action relative"
    >
        <i class="fa-regular fa-bell text-xl"></i>

        {{-- البادج --}}
        <span data-notif-badge
              class="ui-notification-count {{ $unreadCount > 0 ? '' : 'hidden' }}">
            {{ $unreadCount }}
        </span>
    </button>

    {{-- القائمة --}}
    <div
        x-show="openNotif"
        @click.outside="openNotif = false"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="ui-header-dropdown absolute left-0 mt-3 ui-dropdown-panel py-3 z-50"
    >
        {{-- العنوان --}}
        <div class="ui-dropdown-header">
            <h4 class="ui-title text-lg">الإشعارات</h4>
        </div>

        {{-- القائمة --}}
        <div data-notif-list class="max-h-72 overflow-y-auto custom-scroll px-1">
            @forelse($latestNotifications as $n)
                <a href="{{ route('admin.notifications.show', $n->id) }}"
                   class="ui-notification-item {{ $n->isReadBy($auth->id) ? 'opacity-60' : 'ui-notification-item-unread' }}">
                    <div class="flex items-start gap-3">
                        {{-- أيقونة --}}
                        <div class="ui-notification-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>

                        <div class="flex-1">
                            <div class="ui-notification-title">{{ $n->title }}</div>
                            <div class="ui-notification-body">{{ $n->message }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div data-notification-empty-state class="ui-text-muted px-4 py-6 text-center text-sm">
                    لا توجد إشعارات
                </div>
            @endforelse
        </div>

        {{-- زر عرض الكل --}}
        <div class="ui-dropdown-separator">
            <a href="{{ route('admin.notifications.index') }}"
               class="ui-dropdown-footer-link">
                عرض جميع الإشعارات
            </a>
        </div>
    </div>
</div>

                {{-- المستخدم --}}
                <div class="relative">
                    <button
                        @click="openUser = !openUser; openNotif = false"
                        class="flex items-center gap-2"
                    >
                        <img
                            src="https://ui-avatars.com/api/?name={{ urlencode($auth->name) }}"
                            class="ui-avatar"
                        >
                        <i class="fa-solid fa-chevron-down ui-text-muted text-sm"></i>
                    </button>

                    <div
                        x-show="openUser"
                        @click.outside="openUser = false"
                        x-cloak
                        class="absolute left-0 mt-3 w-56 ui-dropdown-panel py-2 z-50"
                    >
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="ui-dropdown-danger-action">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                <span>تسجيل الخروج</span>
                            </button>
                        </form>
                    </div>
                </div>

            </div>

        </div>
    </div>

    {{-- قائمة الجوال الخاصة بالمدير العام --}}
    <div
        x-show="openMenu"
        x-cloak
        class="lg:hidden ui-mobile-menu px-4 py-4 space-y-3"
    >
        <a href="{{ route('admin.security.index') }}"
           class="ui-mobile-action-row {{ request()->routeIs('admin.security.*') ? 'ui-sidebar-link-active' : '' }}">
            <i class="fa-solid fa-shield-halved ui-mobile-action-icon" aria-hidden="true"></i>
            <span>مركز القيادة الأمنية</span>
        </a>
        <a href="{{ route('admin.users.index', ['add' => 1]) }}"
           class="ui-mobile-action-row">
            <i class="fa-solid fa-user-plus ui-mobile-action-icon"></i>
            <span>إضافة مستخدم</span>
        </a>
    </div>

</nav>
