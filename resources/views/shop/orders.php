@extends('layouts.shop')
@set('title', __('My orders'))

@section('content')
<h1 class="h3 mb-4">{{ __('My orders') }}</h1>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Order') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Total') }}</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><a href="{{ route('shop.orders.show', ['order' => $order->id]) }}" class="fw-medium">{{ $order->order_number }}</a></td>
                    <td class="small text-secondary">{{ date('M j, Y', strtotime($order->created_at)) }}</td>
                    <td><span class="badge {{ App\Models\Order::badge($order->status) }}">{{ __(ucfirst($order->status)) }}</span></td>
                    <td class="num">{{ money($order->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-secondary py-4">{{ __('No orders yet.') }} <a href="{{ route('shop.index') }}">{{ __('Start shopping') }}</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
