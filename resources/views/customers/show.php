@extends('layouts.admin')

@set('title', 'Customer')

@section('content')
<a href="<?= url('/customers') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to customers</a>

<div class="row g-3 mt-1">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-1">{{ $customer->name }}</h2>
                <div>{{ $customer->email }}</div>
                <div class="small text-secondary">{{ $customer->phone ?? '' }}</div>
                <hr>
                <div class="d-flex justify-content-between"><span class="text-secondary">Orders</span><span class="num">{{ count($orders) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Total spent</span><span class="num">{{ money($spent) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Customer since</span><span>{{ date('M j, Y', strtotime($customer->created_at)) }}</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Orders</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Order</th><th>Status</th><th>Date</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td><a href="<?= url('/orders/' . $order->id) ?>">{{ $order->order_number }}</a></td>
                            <td><span class="badge <?= App\Models\Order::badge($order->status) ?>">{{ ucfirst($order->status) }}</span></td>
                            <td class="small text-secondary">{{ date('M j, Y', strtotime($order->created_at)) }}</td>
                            <td class="num">{{ money($order->total) }}</td>
                        </tr>
                    @endforeach
                    @if(!$orders)
                        <tr><td colspan="4" class="text-center text-secondary py-4">No orders yet.</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
