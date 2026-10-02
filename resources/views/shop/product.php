{: layout 'layouts.shop' :}
{: block 'title', $product->name :}

{: block 'content' :}
<a href="{{ route('shop.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('Back to the shop') }}</a>

<div class="row g-4 mt-1">
    <div class="col-md-5">
        <div class="product-card">
            <div class="thumb">
                {: if $product->image :}
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
                {: else :}
                    <i class="bi bi-box-seam" style="font-size: 5rem;"></i>
                {: end :}
            </div>
        </div>
    </div>
    <div class="col-md-7">
        {: if $product->category :}
            <a href="{{ route('shop.index', ['category' => $product->category->slug]) }}" class="small text-secondary">{{ $product->category->name }}</a>
        {: end :}
        <h1 class="h2 mt-1">{{ $product->name }}</h1>
        <div class="price-lg my-3">{{ money($product->price) }}</div>
        <p class="text-secondary">{{ $product->description }}</p>

        {: if $product->stock > 0 :}
            <form method="POST" action="{{ route('shop.cart.add') }}" class="d-flex gap-2 align-items-center" style="max-width: 320px;">
                {: csrf :}
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="number" name="quantity" value="1" min="1" max="{{ min(20, $product->stock) }}" class="form-control" style="width: 90px;" aria-label="{{ __('Quantity') }}">
                <button type="submit" class="btn btn-dark flex-grow-1"><i class="bi bi-cart-plus me-1"></i>{{ __('Add to cart') }}</button>
            </form>
            <div class="small text-secondary mt-2">{{ trans_choice('{1} Only :count left|[2,9] Only :count left|[10,*] In stock', $product->stock) }}</div>
        {: else :}
            <span class="badge text-bg-secondary fs-6">{{ __('Out of stock') }}</span>
        {: end :}
    </div>
</div>

{: if $related :}
    <h2 class="h5 mt-5 mb-3">{{ __('You may also like') }}</h2>
    <div class="row g-3">
        {: each $related as $item :}
            <div class="col-6 col-md-3">
                <div class="product-card">
                    <a href="{{ route('shop.product', ['product' => $item->id]) }}" class="thumb">
                        {: if $item->image :} <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" loading="lazy"> {: else :} <i class="bi bi-box-seam"></i> {: end :}
                    </a>
                    <div class="body">
                        <a href="{{ route('shop.product', ['product' => $item->id]) }}" class="name">{{ $item->name }}</a>
                        <span class="price mt-auto pt-2">{{ money($item->price) }}</span>
                    </div>
                </div>
            </div>
        {: end :}
    </div>
{: end :}
{: end :}
