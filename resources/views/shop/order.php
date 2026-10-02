{: layout 'layouts.shop' :}
{: block 'title', $order->order_number :}

{: block 'content' :}
<a href="{{ route('shop.orders') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ __('My orders') }}</a>
<div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-3">
    <h1 class="h3 mb-0">{{ $order->order_number }}</h1>
    <span class="badge {{ App\Models\Order::badge($order->status) }} fs-6">{{ __(ucfirst($order->status)) }}</span>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>{{ __('Item') }}</th><th class="num">{{ __('Price') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Subtotal') }}</th></tr></thead>
                <tbody>
                {: each $items as $item :}
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="num">{{ money($item->unit_price) }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ money($item->unit_price * $item->quantity) }}</td>
                    </tr>
                {: end :}
                </tbody>
                <tfoot><tr><th colspan="3" class="text-end">{{ __('Total') }}</th><th class="num">{{ money($order->total) }}</th></tr></tfoot>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <div class="small text-secondary">{{ __('Shipping address') }}</div>
            <div>{{ $order->shipping_address }}</div>
            <hr>
            <div class="small text-secondary">{{ __('Placed') }}</div>
            <div>{{ date('M j, Y H:i', strtotime($order->created_at)) }}</div>
        </div></div>
    </div>
</div>
{: end :}
