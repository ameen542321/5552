@extends('dashboard.app')

@section('content')
<div class="mx-auto max-w-7xl p-4 sm:p-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-bold ui-title flex items-center gap-2">
            <i class="fa-solid fa-trash-arrow-up ui-status-danger"></i> سلة المحذوفات
        </h1>
        <a href="{{ route('admin.users.index') }}" class="ui-btn ui-btn-secondary w-full sm:w-auto">العودة للقائمة</a>
    </div>

    <div class="ui-table-wrap">
        <table class="ui-table min-w-[560px] text-right" dir="rtl">
            <thead class="ui-surface-muted-bg ui-text-muted ui-text-caption uppercase">
                <tr>
                    <th class="px-6 py-4">التاجر</th>
                    <th class="px-6 py-4 text-center">تاريخ الحذف</th>
                    <th class="px-6 py-4 text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ui-border">
                @forelse($users as $user)
                <tr class="ui-hover-info-bg">
                    <td class="px-6 py-4">
                        <div class="ui-title font-medium">{{ $user->name }}</div>
                        <div class="ui-text-caption ui-text-muted">{{ $user->email }}</div>
                    </td>
                    <td class="px-6 py-4 text-center ui-text-muted text-sm">
                        {{ $user->deleted_at->diffForHumans() }}
                    </td>
                    <td class="px-6 py-4 flex items-center justify-center gap-2">
                        <form action="{{ route('admin.users.restore', $user->id) }}" method="POST"
                              data-ui-confirm="سيُعاد حساب {{ $user->name }} إلى قائمة المستخدمين."
                              data-ui-confirm-title="استعادة المستخدم؟">
                            @csrf
                            <button type="submit" class="p-2 ui-status-success ui-hover-info-bg rounded-lg transition" title="استعادة" aria-label="استعادة المستخدم {{ $user->name }}">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                        </form>

                        <form action="{{ route('admin.users.force-delete', $user->id) }}" method="POST"
                              data-ui-confirm="سيُحذف حساب {{ $user->name }} نهائيًا ولا يمكن التراجع عن العملية."
                              data-ui-confirm-title="حذف المستخدم نهائيًا؟">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 ui-status-danger ui-hover-info-bg rounded-lg transition" title="حذف نهائي" aria-label="حذف المستخدم {{ $user->name }} نهائيًا">
                                <i class="fa-solid fa-eraser"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-6 py-10 text-center ui-text-muted italic">السلة فارغة حالياً</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
