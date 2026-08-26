@extends('dashboard.app')
@section('title', 'إرسال إشعار')

@section('content')
@php
    $oldTargetType = old('target_type', 'accountants');
    $oldTargetIds = collect(old('target_ids', []))->map(fn ($id) => (int) $id)->values()->all();
@endphp

<div class="max-w-3xl mx-auto px-4 py-6" x-data="notificationComposer(@js($oldTargetType), @js($oldTargetIds))">
    <div class="mb-6 flex items-center gap-2">
        <h1 class="text-3xl font-black ui-title">مركز إرسال الإشعارات</h1>
        <x-ui.help title="مركز إرسال الإشعارات" body="إرسال إشعار داخلي للمحاسبين التابعين لك أو للإدارة بشكل سريع وآمن." />
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl border ui-border ui-status-success-bg ui-status-success text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-4 rounded-xl border ui-border ui-status-danger-bg ui-status-danger text-sm">
            <ul class="list-disc mr-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('user.notifications.send.store') }}" class="space-y-4">
        @csrf

        <div class="ui-card p-5 space-y-5">
            <div>
                <label class="ui-text-soft font-bold block mb-2">عنوان الإشعار</label>
                <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required
                    class="w-full p-3 rounded-xl ui-input" placeholder="مثال: تحديث نظام العمل اليومي">
            </div>

            <div>
                <label class="ui-text-soft font-bold block mb-2">نص الإشعار</label>
                <textarea name="message" rows="7" maxlength="2000" required
                    class="w-full p-3 rounded-xl ui-input" placeholder="اكتب محتوى الإشعار هنا...">{{ old('message') }}</textarea>
            </div>
        </div>

        <div class="ui-card p-5 space-y-4">
            <h2 class="ui-title font-black text-lg">تحديد المستلمين</h2>

            <div class="grid grid-cols-1 gap-2">
                <button type="button" @click="setMode('accountants')"
                    :class="mode==='accountants' ? 'ui-border ui-status-info-bg ui-status-info' : 'ui-border ui-surface-strong-bg ui-text-soft'"
                    class="w-full text-right p-3 rounded-xl border transition">
                    <div class="font-bold">عدة محاسبين</div>
                    <div class="ui-text-caption opacity-80">يمكنك تحديد أكثر من محاسب</div>
                </button>

                <button type="button" @click="setMode('accountant')"
                    :class="mode==='accountant' ? 'ui-border ui-status-info-bg ui-status-info' : 'ui-border ui-surface-strong-bg ui-text-soft'"
                    class="w-full text-right p-3 rounded-xl border transition">
                    <div class="font-bold">محاسب واحد</div>
                    <div class="ui-text-caption opacity-80">يتم إرسال الإشعار لمحاسب واحد فقط</div>
                </button>

                <button type="button" @click="setMode('admin')"
                    :class="mode==='admin' ? 'ui-border ui-status-warning-bg ui-status-warning' : 'ui-border ui-surface-strong-bg ui-text-soft'"
                    class="w-full text-right p-3 rounded-xl border transition">
                    <div class="font-bold">المدير العام</div>
                    <div class="ui-text-caption opacity-80">إرسال مباشر للإدارة</div>
                </button>
            </div>

            <input type="hidden" name="target_type" :value="mode">

            <template x-if="mode !== 'admin'">
                <div>
                    <p class="ui-text-caption ui-text-muted mb-2">اختر المحاسبين:</p>
                    <div class="max-h-72 overflow-y-auto space-y-2 pr-1">
                        @forelse($accountants as $acc)
                            <button type="button" @click="toggle({{ (int)$acc->id }})"
                                :class="selected.includes({{ (int)$acc->id }}) ? 'ui-border ui-status-success-bg ui-status-success' : 'ui-border ui-surface-strong-bg ui-text-soft'"
                                class="w-full text-right border rounded-xl p-3 transition">
                                <div class="font-bold">{{ $acc->name }}</div>
                                <div class="ui-text-caption opacity-75">{{ $acc->email ?? $acc->phone ?? 'بدون بيانات إضافية' }}</div>
                            </button>
                        @empty
                            <div class="text-sm ui-text-muted p-3 rounded-xl border ui-border ui-surface-strong-bg">لا يوجد محاسبون نشطون حاليًا.</div>
                        @endforelse
                    </div>

                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="target_ids[]" :value="id">
                    </template>
                </div>
            </template>

            <template x-if="mode==='admin'">
                <p class="text-sm ui-text-muted p-3 rounded-xl border ui-border ui-surface-strong-bg">سيتم إرسال الإشعار إلى حسابات الإدارة مباشرة.</p>
            </template>

            <div class="ui-section-divider ui-section-divider-sm">
                <button class="w-full py-3 rounded-xl ui-btn ui-btn-primary ui-title font-black transition">إرسال الإشعار</button>
            </div>
        </div>
    </form>
</div>

@endsection
