@extends('layouts.admin')

@set('title', 'Customers')

@section('content')
<form method="GET" action="<?= url('/customers') ?>" class="d-flex gap-2 mb-3">
    <input type="search" name="q" value="{{ $search ?? '' }}" class="form-control" placeholder="Name or email" style="width: 240px;">
    <button class="btn btn-outline-secondary" type="submit">Search</button>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Customer</th><th>Phone</th><th class="num">Orders</th><th class="num">Total spent</th><th>Last order</th></tr>
            </thead>
            <tbody>
            @foreach($customers as $customer)
                <tr>
                    <td>
                        <a href="<?= url('/customers/' . $customer['id']) ?>" class="fw-medium">{{ $customer['name'] }}</a>
                        <div class="small text-secondary">{{ $customer['email'] }}</div>
                    </td>
                    <td class="small">{{ $customer['phone'] ?? '' }}</td>
                    <td class="num">{{ $customer['order_count'] }}</td>
                    <td class="num">{{ money($customer['total_spent']) }}</td>
                    <td class="small text-secondary"><?= $customer['last_order_at'] ? e(date('M j, Y', strtotime($customer['last_order_at']))) : '—' ?></td>
                </tr>
            @endforeach
            @if(!$customers)
                <tr><td colspan="5" class="text-center text-secondary py-4">No customers found.</td></tr>
            @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
