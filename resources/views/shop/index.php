@extends('layouts.shop')
@set('title', $category ? $category->name : __('Shop'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $category ? $category->name : __('All products') }}</h1>
        @if($search)
            <div class="text-secondary small">{{ __('Results for ":q"', ['q' => $search]) }} · <a href="{{ route('shop.index') }}">{{ __('Clear') }}</a></div>
        @endif
    </div>
    <span class="text-secondary small">{{ trans_choice('{0} No products|{1} :count product|[2,*] :count products', $products['total']) }}</span>
</div>

<div class="category-pills d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('shop.index') }}" class="btn btn-sm {{ $category ? 'btn-outline-secondary' : 'btn-dark' }}">{{ __('All') }}</a>
    @foreach($categories as $c)
        <a href="{{ route('shop.index', ['category' => $c->slug]) }}" class="btn btn-sm {{ $category && $category->id === $c->id ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $c->name }}</a>
    @endforeach
</div>

<div class="row g-3">
    @forelse($products['data'] as $product)
        <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card">
                <a href="{{ route('shop.product', ['product' => $product->id]) }}" class="thumb">
                    @if($product->image)
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy">
                    @else
                        <i class="bi bi-box-seam"></i>
                    @endif
                </a>
                <div class="body">
                    <a href="{{ route('shop.product', ['product' => $product->id]) }}" class="name">{{ $product->name }}</a>
                    <div class="d-flex justify-content-between align-items-center mt-auto pt-2">
                        <span class="price">{{ money($product->price) }}</span>
                        @if($product->stock > 0)
                            <form method="POST" action="{{ route('shop.cart.add') }}" class="m-0">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button class="btn btn-sm btn-dark" type="submit" aria-label="{{ __('Add to cart') }}"><i class="bi bi-cart-plus"></i></button>
                            </form>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Out of stock') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-secondary py-5">{{ __('No products found.') }}</div>
    @endforelse
</div>

@php($page = $products)
@include('partials.pagination')
@endsection
