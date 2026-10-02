{: layout 'layouts.shop' :}
{: block 'title', __('Cart') :}

{: block 'content' :}
<h1 class="h3 mb-4">{{ __('Your cart') }}</h1>

{: if !$lines :}
    <div class="text-center py-5">
        <i class="bi bi-cart3 display-4 text-secondary"></i>
        <p class="mt-3 text-secondary">{{ __('Your cart is empty.') }}</p>
        <a href="{{ route('shop.index') }}" class="btn btn-dark">{{ __('Start shopping') }}</a>
    </div>
{: else :}
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <ul class="list-group list-group-flush">
                {: each $lines as $line :}
                    {: php $p = $line['product'] :}
                    <li class="list-group-item d-flex gap-3 align-items-center py-3">
                        <a href="{{ route('shop.product', ['product' => $p->id]) }}" class="flex-shrink-0">
                            {: if $p->image :}
                                <img src="{{ $p->imageUrl() }}" alt="" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
                            {: else :}
                                <div class="rounded bg-light border d-flex align-items-center justify-content-center text-secondary" style="width: 64px; height: 64px;"><i class="bi bi-box-seam"></i></div>
                            {: end :}
                        </a>
                        <div class="flex-grow-1">
                            <a href="{{ route('shop.product', ['product' => $p->id]) }}" class="fw-semibold text-body text-decoration-none">{{ $p->name }}</a>
                            <div class="small text-secondary">{{ money($p->price) }}</div>
                        </div>
                        <form method="POST" action="{{ route('shop.cart.update', ['product' => $p->id]) }}" class="d-flex gap-1">
                            {: csrf :}
                            {: method 'PATCH' :}
                            <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="{{ min(20, $p->stock) }}" class="form-control form-control-sm" style="width: 70px;" aria-label="{{ __('Quantity') }}">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('Update') }}</button>
                        </form>
                        <div class="num fw-semibold" style="width: 90px;">{{ money($line['subtotal']) }}</div>
                        <form method="POST" action="{{ route('shop.cart.remove', ['product' => $p->id]) }}" class="m-0">
                            {: csrf :}
                            {: method 'DELETE' :}
                            <button class="btn btn-sm btn-link text-danger" type="submit" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </li>
                {: end :}
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between"><span>{{ __('Subtotal') }}</span><span class="num">{{ money($total) }}</span></div>
                <div class="d-flex justify-content-between text-secondary small"><span>{{ __('Shipping') }}</span><span>{{ __('Free') }}</span></div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5"><span>{{ __('Total') }}</span><span class="num">{{ money($total) }}</span></div>
                <a href="{{ route('shop.checkout') }}" class="btn btn-dark w-100 mt-3">{{ __('Checkout') }}</a>
            </div>
        </div>
    </div>
</div>
{: end :}
{: end :}
