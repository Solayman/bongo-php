@extends('layouts.admin')

@set('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Revenue (all time)</div>
            <div class="value">{{ money($stats['revenue']) }}</div>
            <div class="sub">{{ money($stats['revenue30']) }} in the last 30 days</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Orders</div>
            <div class="value">{{ number_format($stats['orders']) }}</div>
            <div class="sub"><a href="<?= url('/orders?status=pending') ?>">{{ $stats['pending'] }} pending</a></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Customers</div>
            <div class="value">{{ number_format($stats['customers']) }}</div>
            <div class="sub"><a href="<?= url('/customers') ?>">View customers</a></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Products</div>
            <div class="value">{{ number_format($stats['products']) }}</div>
            <div class="sub">
                <?php if ($stats['lowStock']): ?>
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i> {{ $stats['lowStock'] }} low on stock
                <?php else: ?>
                    All products in stock
                <?php endif; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent orders</span>
                <a href="<?= url('/orders') ?>" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Order</th><th>Customer</th><th>Status</th><th>Date</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><a href="<?= url('/orders/' . $order->id) ?>">{{ $order->order_number }}</a></td>
                            <td>{{ $order->customer_name ?? 'Guest' }}</td>
                            <td><span class="badge <?= App\Models\Order::badge($order->status) ?>">{{ ucfirst($order->status) }}</span></td>
                            <td class="text-secondary small">{{ date('M j, Y', strtotime($order->created_at)) }}</td>
                            <td class="num">{{ money($order->total) }}</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentOrders): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-4">No orders yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Orders by status</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($statusCounts as $status => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= url('/orders?status=' . $status) ?>" class="text-body text-decoration-none">
                            <span class="badge <?= App\Models\Order::badge($status) ?> me-2">&nbsp;</span>{{ ucfirst($status) }}
                        </a>
                        <span class="num">{{ $count }}</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Top products</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($topProducts as $product): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $product['product_name'] }}</span>
                        <span class="text-secondary small num">{{ $product['units'] }} sold · {{ money($product['revenue']) }}</span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$topProducts): ?>
                    <li class="list-group-item text-secondary">No sales yet.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Low stock</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($lowStock as $product): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="<?= url('/products/' . $product->id . '/edit') ?>">{{ $product->name }}</a>
                        <span class="num <?= $product->stock == 0 ? 'text-danger fw-semibold' : '' ?>">
                            <?= $product->stock == 0 ? 'Out of stock' : e($product->stock) . ' left' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$lowStock): ?>
                    <li class="list-group-item text-secondary">Nothing is running low.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
@endsection
