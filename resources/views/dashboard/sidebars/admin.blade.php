{{-- إصلاح مطبق: السايدبار يعرض الوجهات الفعلية فقط ويدعم حالات التوسيع بإتاحة واضحة. --}}
<div
    x-data="{ open: false }"
    class="ui-sidebar hidden min-h-screen flex-col transition-all duration-300 lg:flex"
    :class="open ? 'w-64' : 'w-20'"
>

    {{-- زر الطي --}}
    <div class="flex justify-end p-3">
        <button
            type="button"
            @click="open = !open"
            class="ui-sidebar-toggle transition-transform duration-300"
            :class="open ? 'rotate-180' : ''"
            :aria-expanded="open"
            aria-label="توسيع أو طي القائمة الجانبية"
        >
            <i class="fa-solid fa-angles-right"></i>
        </button>
    </div>

    {{-- عنوان --}}
    <h2 class="ui-sidebar-title text-center text-xl mb-6 transition-opacity duration-300"
        x-show="open" x-cloak>
        المدير العام
    </h2>

    {{-- العناصر --}}
    <ul class="space-y-2 px-3">

        {{-- الرئيسية --}}
        <li>
            <a href="{{ route('admin.dashboard.index') }}"
                class="ui-sidebar-link
                       {{ request()->routeIs('admin.dashboard.index') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-house w-6 text-lg"></i>
                <span x-show="open" x-cloak>الرئيسية</span>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.security.index') }}"
                class="ui-sidebar-link group relative {{ request()->routeIs('admin.security.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}"
                aria-label="مركز القيادة الأمنية">
                <i class="fa-solid fa-shield-halved w-6 text-lg"></i>
                <span x-show="open" x-cloak>القيادة الأمنية</span>
                <span x-show="!open" x-cloak class="ui-tooltip-popover">القيادة الأمنية</span>
            </a>
        </li>


        {{-- فحص بيانات الأجل --}}
        <li>
            <a href="{{ route('admin.health.credit') }}"
                class="ui-sidebar-link
                       {{ request()->routeIs('admin.health.credit') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-heart-pulse w-6 text-lg"></i>
                <span x-show="open" x-cloak>فحص بيانات الأجل</span>
            </a>
        </li>

        {{-- فحص بيانات المديونية --}}
        <li>
            <a href="{{ route('admin.health.debt') }}"
                class="ui-sidebar-link
                       {{ request()->routeIs('admin.health.debt') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-file-invoice-dollar w-6 text-lg"></i>
                <span x-show="open" x-cloak>فحص بيانات المديونية</span>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.health.purchase-orders') }}"
                class="ui-sidebar-link {{ request()->routeIs('admin.health.purchase-orders') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-clipboard-check w-6 text-lg"></i>
                <span x-show="open" x-cloak>فحص طلبيات التوريد</span>
            </a>
        </li>

        {{-- إدارة المستخدمين --}}
        <li x-data="{ dropUsers: {{ request()->routeIs('admin.users.*') ? 'true' : 'false' }} }">

            <button
                type="button"
                @click="dropUsers = !dropUsers"
                class="ui-sidebar-link justify-between w-full
                       {{ request()->routeIs('admin.users.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}"
                :aria-expanded="dropUsers"
                aria-controls="admin-users-menu"
            >
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-users w-6 text-lg"></i>
                    <span x-show="open" x-cloak>المستخدمين</span>
                </div>

                <i class="fa-solid fa-chevron-down transition-transform"
                   :class="dropUsers ? 'rotate-180' : ''"
                   x-show="open" x-cloak></i>
            </button>

            <ul id="admin-users-menu" x-show="dropUsers" x-cloak class="mt-2 space-y-2 pr-3 text-sm ui-sidebar-link-muted" x-transition.opacity>
                <li>
                    <a href="{{ route('admin.users.index') }}"
                        class="ui-sidebar-link
                               {{ request()->routeIs('admin.users.index') ? 'ui-sidebar-link-active' : '' }}">
                        <i class="fa-solid fa-circle-dot w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>عرض المستخدمين</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.users.index', ['add' => 1]) }}"
                        class="ui-sidebar-link
                               {{ request()->routeIs('admin.users.index') && request()->boolean('add') ? 'ui-sidebar-link-active' : '' }}">
                        <i class="fa-solid fa-circle-plus w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>إضافة مستخدم</span>
                    </a>
                </li>
            </ul>

        </li>

        <li>
            <a href="{{ route('admin.support.index') }}"
                class="ui-sidebar-link {{ request()->routeIs('admin.support.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-headset w-6 text-lg"></i>
                <span x-show="open" x-cloak>جلسات الدعم</span>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.support-tickets.index') }}"
                class="ui-sidebar-link {{ request()->routeIs('admin.support-tickets.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-ticket w-6 text-lg"></i>
                <span x-show="open" x-cloak>طلبات الدعم</span>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.support.archive.index') }}"
                class="ui-sidebar-link {{ request()->routeIs('admin.support.archive.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-box-archive w-6 text-lg"></i>
                <span x-show="open" x-cloak>سجل المحذوفات</span>
            </a>
        </li>

        <li>
            <a href="{{ route('admin.support-actions.index') }}"
                class="ui-sidebar-link {{ request()->routeIs('admin.support-actions.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}">
                <i class="fa-solid fa-list-check w-6 text-lg"></i>
                <span x-show="open" x-cloak>إجراءات</span>
            </a>
        </li>

        {{-- الإشعارات --}}
      <li x-data="{ dropNotifs: {{ request()->routeIs('admin.notifications.*') ? 'true' : 'false' }} }">


            <button
                @click="dropNotifs = !dropNotifs"
                class="ui-sidebar-link justify-between w-full
                       {{ request()->routeIs('admin.notifications.*') ? 'ui-sidebar-link-active' : 'ui-sidebar-link-rest' }}"
            >
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-bell w-6 text-lg"></i>
                    <span x-show="open" x-cloak>الإشعارات</span>
                </div>

                <i class="fa-solid fa-chevron-down transition-transform"
                   :class="dropNotifs ? 'rotate-180' : ''"
                   x-show="open" x-cloak></i>
            </button>

            <ul x-show="dropNotifs" x-cloak class="mt-2 space-y-2 pr-3 text-sm ui-sidebar-link-muted" x-transition.opacity>

                <li>
                    <a href="{{ route('admin.notifications.index') }}"
                        class="ui-sidebar-link">
                        <i class="fa-solid fa-circle-dot w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>مركز الإشعارات</span>
                    </a>
                </li>

                <li>
                   <a href="{{ route('notifications.internal.send') }}"


                        class="ui-sidebar-link">
                        <i class="fa-solid fa-paper-plane w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>إرسال إشعار داخلي</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.onesignal.index') }}"
                        class="ui-sidebar-link">
                        <i class="fa-solid fa-satellite-dish w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>إعدادات OneSignal</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.notifications.push') }}"
                        class="ui-sidebar-link">
                        <i class="fa-solid fa-broadcast-tower w-5 ui-text-caption"></i>
                        <span x-show="open" x-cloak>إرسال إشعار OneSignal</span>
                    </a>
                </li>

            </ul>

        </li>

    </ul>

</div>
