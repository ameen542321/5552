@extends('dashboard.app')
@section('title', 'تعديل المتجر - ' . $store->name)

@section('content')
    @include('user.stores.includes.store-form', ['store' => $store])
@endsection
