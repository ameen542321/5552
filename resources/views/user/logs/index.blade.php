@extends('dashboard.app')

@section('title', 'سجل العمليات')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6" data-user-logs>

    <div class="text-center sm:text-right">
        <h1 class="text-2xl font-bold ui-title">سجل العمليات</h1>
    </div>

    <form method="GET" class="grid grid-cols-1 gap-4 rounded-lg ui-surface-muted-bg p-4 sm:grid-cols-2 lg:grid-cols-4">

        <div>
            <label class="mb-1 block text-sm ui-text-soft">نوع العملية</label>
            <select name="action" class="ui-input w-full rounded-lg px-3 py-2">
                <option value="">الكل</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                        {{ $action }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm ui-text-soft">من تاريخ</label>
            <input type="date" name="from_date"
                   value="{{ request('from_date') }}"
                   class="ui-input w-full rounded-lg px-3 py-2">
        </div>

        <div>
            <label class="mb-1 block text-sm ui-text-soft">إلى تاريخ</label>
            <input type="date" name="to_date"
                   value="{{ request('to_date') }}"
                   class="ui-input w-full rounded-lg px-3 py-2">
        </div>

        <div class="flex items-end">
            <button class="ui-btn ui-btn-primary w-full rounded-lg px-4 py-2 ui-title">
                تطبيق الفلاتر
            </button>
        </div>

    </form>

    <div class="overflow-x-auto rounded-lg ui-surface-muted-bg shadow">
        <table class="w-full min-w-[720px] ui-text-soft">
            <thead class="ui-surface-muted-bg ui-text-muted">
                <tr>
                    <th class="px-4 py-3 text-right">العملية</th>
                    <th class="px-4 py-3 text-right">الوصف</th>
                    <th class="px-4 py-3 text-right">التاريخ</th>
                    <th class="px-4 py-3 text-right">تفاصيل</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-b ui-border ui-surface-muted-bg">

                        <td class="px-4 py-3 font-semibold">
                            <span class="px-2 py-1 rounded ui-status-info-bg ui-title text-sm">
                                {{ $log->action_label }}
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            <span title="{{ $log->description }}">
                                {{ $log->snippet }}
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            {{ $log->created_at->format('Y-m-d H:i') }}
                        </td>

                        <td class="px-4 py-3">
                            <button type="button"
                                    class="ui-btn ui-btn-info px-3 py-2 text-sm"
                                    data-log-details-trigger
                                    data-log-user-name="{{ $log->user?->name }}"
                                    data-log-store-name="{{ $log->store?->name }}"
                                    data-log-store-id="{{ $log->store?->id }}"
                                    data-log-details="{{ json_encode($log->details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
                                عرض
                            </button>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-6 ui-text-muted">
                            لا توجد سجلات
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $logs->links('pagination::tailwind') }}
    </div>

</div>

@endsection
