@extends('layouts.admin')

@set('title', __('Dashboard'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">{{ __('Revenue (all time)') }}</div>
            <div class="value">{{ money($stats['revenue']) }}</div>
            <div class="sub">{{ __(':amount in the last 30 days', ['amount' => money($stats['revenue30'])]) }}</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">{{ __('Orders') }}</div>
            <div class="value">{{ number_format($stats['orders']) }}</div>
            <div class="sub"><a href="<?= url('/orders?status=pending') ?>">{{ __(':count pending', ['count' => $stats['pending']]) }}</a></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">{{ __('Customers') }}</div>
            <div class="value">{{ number_format($stats['customers']) }}</div>
            <div class="sub"><a href="<?= url('/customers') ?>">{{ __('View customers') }}</a></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">{{ __('Products') }}</div>
            <div class="value">{{ number_format($stats['products']) }}</div>
            <div class="sub">
                @if($stats['lowStock'])
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i> {{ trans_choice('{0} All products in stock|{1} :count product low on stock|[2,*] :count products low on stock', $stats['lowStock']) }}
                @else
                    {{ trans_choice('{0} All products in stock|{1} :count product low on stock|[2,*] :count products low on stock', 0) }}
                @endif
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ __('Recent orders') }}</span>
                <a href="<?= url('/orders') ?>" class="small">{{ __('View all') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                    @foreach($recentOrders as $order)
                        <tr>
                            <td><a href="<?= url('/orders/' . $order->id) ?>">{{ $order->order_number }}</a></td>
                            <td>{{ $order->customer_name ?? __('Guest') }}</td>
                            <td><span class="badge <?= App\Models\Order::badge($order->status) ?>">{{ __(ucfirst($order->status)) }}</span></td>
                            <td class="text-secondary small">{{ date('M j, Y', strtotime($order->created_at)) }}</td>
                            <td class="num">{{ money($order->total) }}</td>
                        </tr>
                    @endforeach
                    @if(!$recentOrders)
                        <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('No orders yet.') }}</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header bg-white fw-semibold">{{ __('Orders by status') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($statusCounts as $status => $count)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= url('/orders?status=' . $status) ?>" class="text-body text-decoration-none">
                            <span class="badge <?= App\Models\Order::badge($status) ?> me-2">&nbsp;</span>{{ __(ucfirst($status)) }}
                        </a>
                        <span class="num">{{ $count }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">{{ __('Top products') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($topProducts as $product)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $product['product_name'] }}</span>
                        <span class="text-secondary small num">{{ __(':units sold', ['units' => $product['units']]) }} · {{ money($product['revenue']) }}</span>
                    </li>
                @endforeach
                @if(!$topProducts)
                    <li class="list-group-item text-secondary">{{ __('No sales yet.') }}</li>
                @endif
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">{{ __('Low stock') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($lowStock as $product)
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= url('/products/' . $product->id . '/edit') ?>">{{ $product->name }}</a>
                        <span class="num <?= $product->stock == 0 ? 'text-danger fw-semibold' : '' ?>">
                            {{ $product->stock == 0 ? __('Out of stock') : __(':count left', ['count' => $product->stock]) }}
                        </span>
                    </li>
                @endforeach
                @if(!$lowStock)
                    <li class="list-group-item text-secondary">{{ __('Nothing is running low.') }}</li>
                @endif
            </ul>
        </div>
    </div>
</div>
@endsection
