@extends('dashboard.app')

@section('content')
<div class="max-w-3xl mx-auto py-8">

    <h2 class="text-2xl font-bold ui-title mb-6">إعدادات OneSignal</h2>

    @if(session('success'))
        <div class="ui-status-success-bg ui-status-success ui-title p-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="ui-status-danger-bg ui-status-danger ui-title p-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.onesignal.update') }}"
          class="ui-card p-6 mb-6">
        @csrf

        <div class="mb-4">
            <label class="ui-text-soft font-semibold">App ID</label>
            <input type="text" name="app_id"
                   value="{{ $settings->app_id ?? '' }}"
                   class="w-full mt-2 p-2 ui-surface-strong-bg ui-text-soft rounded border ui-border">
        </div>

        <div class="mb-4">
            <label class="ui-text-soft font-semibold">API Key</label>
            <input type="text" name="api_key"
                   value="{{ $settings->api_key ?? '' }}"
                   class="w-full mt-2 p-2 ui-surface-strong-bg ui-text-soft rounded border ui-border">
        </div>

        <button class="ui-btn ui-btn-primary ui-title px-4 py-2 rounded">
            حفظ الإعدادات
        </button>
    </form>

    <form method="POST" action="{{ route('admin.onesignal.test') }}"
          class="ui-card p-6">
        @csrf
        <button class="ui-btn ui-btn-primary ui-title px-4 py-2 rounded">
            إرسال إشعار تجريبي
        </button>
    </form>

</div>
@endsection
