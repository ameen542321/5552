@extends('dashboard.app')
@section('title', 'إرسال نقل مخزني')
@section('content')
    <x-store-transfer-form
        :store="$store"
        :stores="$stores"
        :products="$products"
        :current-business-date="$currentBusinessDate"
        :action="route('accountant.transfers.store')"
        :back-url="route('accountant.transfers.index')"
        title="إرسال نقل مخزني" />
@endsection
