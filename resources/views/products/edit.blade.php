@extends('layouts.app')
@section('title', 'Edit Product')
@section('crumb', $product->name)

@section('content')
<div class="page-head">
    <div class="titles"><h2>{{ __('Edit Product') }}</h2><p>{{ $product->name }} · {{ $product->sku }}</p></div>
    <div class="actions"><a href="{{ route('products.index') }}" class="btn btn-light">@include('partials.icon',['name'=>'arrow-left']) {{ __('Back') }}</a></div>
</div>
<form method="POST" action="{{ route('products.update',$product) }}">
    @csrf @method('PUT')
    @include('products._form')
</form>
@endsection
