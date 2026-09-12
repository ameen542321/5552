@extends('dashboard.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6" dir="rtl">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="ui-title text-2xl font-bold">فحص سلامة طلبيات التوريد</h1>
            <p class="ui-text-soft mt-1">فحص يدوي للأدمن فقط؛ يعرض الحالات المتناقضة ولا يغير أي بيانات أو مخزون.</p>
        </div>
        <div class="ui-card-muted px-5 py-3 text-center">
            <span class="ui-text-soft block">الطلبيات التي تحتاج مراجعة</span>
            <strong class="{{ $totalIssues > 0 ? 'ui-status-danger' : 'ui-status-success' }} text-3xl">{{ number_format($totalIssues) }}</strong>
        </div>
    </div>

    <div class="ui-table-wrap">
        <table class="ui-table min-w-[900px]">
            <thead><tr><th>الطلبية</th><th>المتجر</th><th>الحالة العامة</th><th>المرحلة</th><th>المشاكل</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><strong class="ui-title">{{ $row['reference'] }}</strong>@if($row['deleted']) <span class="ui-badge ui-badge-danger">محذوفة</span>@endif</td>
                        <td>{{ $row['store'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ \App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow::label($row['workflow_status']) }}</td>
                        <td><ul class="space-y-1">@foreach($row['problems'] as $problem)<li class="ui-status-danger">{{ $problem }}</li>@endforeach</ul></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ui-status-success py-8 text-center">لا توجد حالات متناقضة ضمن آخر 500 طلبية.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
