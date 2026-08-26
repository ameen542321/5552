@extends('dashboard.app')

@section('content')
{{-- إصلاح مطبق: منتقي المستلمين يستخدم وحدة مشتركة بلا script أو HTML ديناميكي غير آمن داخل Blade. --}}
<div class="p-6 space-y-6" data-admin-notification-recipients>

    <h1 class="text-3xl font-bold ui-title mb-4">إرسال إشعار داخلي</h1>

    <form action="{{ route('notifications.internal.send.store') }}" method="POST" class="space-y-6">
        @csrf

        {{-- بطاقة اختيار الفئة --}}
        <div class="ui-surface-muted-bg ui-border rounded-xl p-6">
            <h2 class="text-xl font-semibold ui-title mb-4">الفئة المستهدفة</h2>

            <select name="target_type" id="target_type" data-target-type
                class="w-full ui-input rounded-lg p-3">
                <option value="all">جميع المستخدمين</option>
                <option value="users">المستخدمين فقط</option>
                <option value="accountants">المحاسبين فقط</option>
            </select>
        </div>

        {{-- بطاقة المستلمين --}}
        <div class="ui-surface-muted-bg ui-border rounded-xl p-6">

            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold ui-title">المستلمون</h2>
                <span id="selectedCount" data-selected-count class="ui-text-soft text-sm">0 مختار</span>
            </div>

            {{-- أدوات التحكم --}}
            <div class="flex items-center gap-3 mb-4">
                <button type="button" id="selectAll" data-select-all
                    class="ui-btn ui-btn-primary text-sm">
                    اختيار الكل
                </button>

                <button type="button" id="clearAll" data-clear-all
                    class="ui-btn ui-btn-danger text-sm">
                    إلغاء الكل
                </button>

                <select id="filterType" data-filter-type
                    class="ui-card ui-title rounded-lg p-2 text-sm">
                    <option value="all">الكل</option>
                    <option value="user">المستخدمين</option>
                    <option value="accountant">المحاسبين</option>
                </select>
            </div>

            {{-- مربع البحث --}}
            <input type="text" id="recipientSearch" data-recipient-search
                class="w-full ui-card ui-title rounded-lg p-3 mb-4"
                placeholder="ابحث عن مستخدم أو محاسب...">

            {{-- التاجز المختارة --}}
            <div id="selectedRecipients" data-selected-recipients class="flex flex-wrap gap-2 mb-4"></div>

            {{-- القائمة --}}
            <div id="recipientList" data-recipient-list class="space-y-2 max-h-64 overflow-y-auto">

                @foreach($users as $user)
                    <div class="recipient-item flex items-center gap-3 p-3 ui-surface-muted-bg rounded-lg cursor-pointer ui-hover-info-bg"
                        data-id="{{ $user->id }}"
                        data-name="{{ $user->name }}"
                        data-type="user" data-recipient-item>

                        <div class="w-10 h-10 rounded-full ui-btn ui-btn-primary flex items-center justify-center ui-title">
                            {{ mb_substr($user->name, 0, 1) }}
                        </div>

                        <div>
                            <div class="ui-title font-semibold">{{ $user->name }}</div>
                            <div class="ui-text-muted text-sm">مستخدم</div>
                        </div>
                    </div>
                @endforeach

                @foreach($accountants as $acc)
                    <div class="recipient-item flex items-center gap-3 p-3 ui-surface-muted-bg rounded-lg cursor-pointer ui-hover-info-bg"
                        data-id="{{ $acc->id }}"
                        data-name="{{ $acc->name }}"
                        data-type="accountant" data-recipient-item>

                        <div class="w-10 h-10 rounded-full ui-status-success-bg ui-status-success flex items-center justify-center ui-title">
                            {{ mb_substr($acc->name, 0, 1) }}
                        </div>

                        <div>
                            <div class="ui-title font-semibold">{{ $acc->name }}</div>
                            <div class="ui-text-muted text-sm">محاسب</div>
                        </div>
                    </div>
                @endforeach

            </div>

            {{-- الحقل المخفي --}}
            <input type="hidden" name="target_ids" id="target_ids" data-target-ids>
        </div>

        {{-- بطاقة كتابة الإشعار --}}
        <div class="ui-surface-muted-bg ui-border rounded-xl p-6">
            <h2 class="text-xl font-semibold ui-title mb-4">محتوى الإشعار</h2>

            <div class="space-y-4">
                <div>
                    <label class="ui-text-soft mb-1 block">عنوان الإشعار</label>
                    <input type="text" name="title"
                        class="w-full ui-input rounded-lg p-3"
                        placeholder="مثال: تحديث مهم في النظام">
                </div>

                <div>
                    <label class="ui-text-soft mb-1 block">نص الإشعار</label>
                    <textarea name="message" rows="5"
                        class="w-full ui-input rounded-lg p-3"
                        placeholder="اكتب محتوى الإشعار هنا..."></textarea>
                </div>

                <button
                    class="ui-btn ui-btn-primary transition ui-title px-6 py-3 rounded-lg font-semibold text-lg">
                    إرسال الإشعار الآن
                </button>
            </div>
        </div>

    </form>

</div>

@endsection
