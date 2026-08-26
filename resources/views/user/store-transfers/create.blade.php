@extends('dashboard.app')
@section('title', 'إنشاء نقل مخزني')
@section('content')
    <x-store-transfer-form
        :store="$store"
        :stores="$stores"
        :products="$products"
        :current-business-date="$currentBusinessDate"
        :action="route('user.stores.transfers.store', $store->id)"
        :back-url="route('user.stores.transfers.index', $store->id)"
        title="إنشاء طلب نقل مخزني" />
@endsection
