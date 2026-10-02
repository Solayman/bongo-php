<!DOCTYPE html>
<html lang="{{ locale() }}">
<body style="margin:0;padding:24px;background:#fafaf9;font-family:Arial,Helvetica,sans-serif;color:#1c1917;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e7e5e4;border-radius:8px;">
    <tr><td style="padding:28px;">
        <h1 style="font-size:20px;margin:0 0 16px;">{{ config('app.name') }}</h1>
        <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $customer->name]) }}</p>
        <p style="margin:0 0 20px;">{{ __('Thank you for your order. We will let you know when it ships.') }}</p>
        <p style="margin:0 0 8px;font-weight:bold;">{{ __('Order') }} {{ $order->order_number }}</p>
        <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            @foreach($items as $item)
                <tr style="border-bottom:1px solid #f5f5f4;">
                    <td>{{ $item->product_name }} × {{ $item->quantity }}</td>
                    <td align="right">{{ money($item->unit_price * $item->quantity) }}</td>
                </tr>
            @endforeach
            <tr><td style="font-weight:bold;">{{ __('Total') }}</td><td align="right" style="font-weight:bold;">{{ money($order->total) }}</td></tr>
        </table>
        <p style="margin:20px 0 0;font-size:13px;color:#78716c;">{{ __('Shipping address') }}: {{ $order->shipping_address }}</p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
