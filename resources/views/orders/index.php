@extends('layouts.admin')

@set('title', 'Orders')

@section('content')
<form method="GET" action="<?= url('/orders') ?>" class="d-flex flex-wrap gap-2 mb-3">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Order number or customer" style="width: 240px;">
    <select name="status" class="form-select" style="width: 160px;">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $status): ?>
            <option value="{{ $status }}" <?= $filters['status'] === $status ? 'selected' : '' ?>>{{ ucfirst($status) }}</option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit">Filter</button>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Order</th><th>Customer</th><th>Status</th><th>Date</th><th class="num">Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders['data'] as $order): ?>
                <tr>
                    <td><a href="<?= url('/orders/' . $order->id) ?>" class="fw-medium">{{ $order->order_number }}</a></td>
                    <td>
                        {{ $order->customer_name ?? 'Guest' }}
                        <div class="small text-secondary">{{ $order->customer_email ?? '' }}</div>
                    </td>
                    <td><span class="badge <?= App\Models\Order::badge($order->status) ?>">{{ ucfirst($order->status) }}</span></td>
                    <td class="text-secondary small">{{ date('M j, Y H:i', strtotime($order->created_at)) }}</td>
                    <td class="num">{{ money($order->total) }}</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders['data']): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4">No orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $page = $orders; ?>
@include('partials.pagination')
@endsection
