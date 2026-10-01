@extends('layouts.admin')

@set('title', 'Order details')

@section('content')
<a href="<?= url('/orders') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to orders</a>

<div class="d-flex flex-wrap justify-content-between align-items-center mt-2 mb-3 gap-2">
    <h2 class="h4 mb-0">
        {{ $order->order_number }}
        <span class="badge <?= App\Models\Order::badge($order->status) ?> align-middle fs-6">{{ ucfirst($order->status) }}</span>
    </h2>
    <form method="POST" action="<?= route('orders.status', ['id' => $order->id]) ?>" class="d-flex gap-2">
        <?= csrf_field() ?>
        <?= method_field('PATCH') ?>
        <select name="status" class="form-select" aria-label="Order status">
            <?php foreach ($statuses as $status): ?>
                <option value="{{ $status }}" <?= $order->status === $status ? 'selected' : '' ?>>{{ ucfirst($status) }}</option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-dark text-nowrap" type="submit">Update status</button>
    </form>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Item</th><th class="num">Unit price</th><th class="num">Qty</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item->product_id): ?>
                                    <a href="<?= url('/products/' . $item->product_id . '/edit') ?>">{{ $item->product_name }}</a>
                                <?php else: ?>
                                    {{ $item->product_name }} <span class="small text-secondary">(deleted product)</span>
                                <?php endif; ?>
                            </td>
                            <td class="num">{{ money($item->unit_price) }}</td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">{{ money($item->unit_price * $item->quantity) }}</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Total</th><th class="num">{{ money($order->total) }}</th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Customer</div>
            <div class="card-body">
                <?php if ($order->user_id): ?>
                    <div class="fw-medium"><a href="<?= url('/customers/' . $order->user_id) ?>">{{ $order->customer_name }}</a></div>
                    <div class="small">{{ $order->customer_email }}</div>
                    <div class="small text-secondary">{{ $order->customer_phone ?? '' }}</div>
                <?php else: ?>
                    <div class="text-secondary">Guest</div>
                <?php endif; ?>
                <hr>
                <div class="small text-secondary">Shipping address</div>
                <div>{{ $order->shipping_address ?? '—' }}</div>
                <hr>
                <div class="small text-secondary">Placed</div>
                <div>{{ date('M j, Y H:i', strtotime($order->created_at)) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
