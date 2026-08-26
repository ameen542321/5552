@extends('dashboard.app')

@section('content')

<div class="max-w-4xl mx-auto" data-store-management>

    {{-- العنوان --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold ui-title">
            سلة المحذوفات
        </h1>

        {{-- زر الرجوع --}}
        <a href="{{ route('user.stores.index') }}"
           class="px-4 py-2 ui-btn ui-btn-secondary ui-title rounded-lg transition">
            رجوع للمتاجر
        </a>
    </div>

    {{-- إذا لا يوجد متاجر محذوفة --}}
    @if($stores->count() === 0)
        <div class="ui-card p-6 text-center ui-text-muted">
            لا يوجد متاجر محذوفة.
        </div>
    @endif

    {{-- عرض المتاجر المحذوفة --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($stores as $store)
            @include('user.stores.includes.store-card', ['store' => $store])
        @endforeach
    </div>

</div>

@endsection
