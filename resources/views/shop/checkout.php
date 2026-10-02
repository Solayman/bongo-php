{: layout 'layouts.shop' :}
{: block 'title', __('Checkout') :}

{: block 'content' :}
<h1 class="h3 mb-4">{{ __('Checkout') }}</h1>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('shop.checkout.place') }}" novalidate>
                {: csrf :}
                <div class="mb-3">
                    <label class="form-label">{{ __('Name') }}</label>
                    <input class="form-control" value="{{ $customer->name }}" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Email') }}</label>
                    <input class="form-control" value="{{ $customer->email }}" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="phone">{{ __('Phone') }}</label>
                    <input class="form-control {: error 'phone' :} is-invalid {: end :}" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}">
                    {: error 'phone' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
                </div>
                <div class="mb-3">
                    <label class="form-label" for="shipping_address">{{ __('Shipping address') }}</label>
                    <textarea class="form-control {: error 'shipping_address' :} is-invalid {: end :}" id="shipping_address" name="shipping_address" rows="3">{{ old('shipping_address') }}</textarea>
                    {: error 'shipping_address' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
                </div>
                <div class="small text-secondary mb-3"><i class="bi bi-cash-coin me-1"></i>{{ __('Payment: cash on delivery') }}</div>
                <button type="submit" class="btn btn-dark w-100">{{ __('Place order') }} · {{ money($total) }}</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-semibold">{{ __('Order summary') }}</div>
            <ul class="list-group list-group-flush">
                {: each $lines as $line :}
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $line['product']->name }} × {{ $line['quantity'] }}</span>
                        <span class="num">{{ money($line['subtotal']) }}</span>
                    </li>
                {: end :}
                <li class="list-group-item d-flex justify-content-between fw-bold">
                    <span>{{ __('Total') }}</span><span class="num">{{ money($total) }}</span>
                </li>
            </ul>
        </div>
    </div>
</div>
{: end :}
