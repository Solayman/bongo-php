<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cart;
use System\Auth\Auth;
use System\Database\DB;
use System\Events\Event;
use System\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(protected Cart $cart)
    {
    }

    /** GET /shop/checkout */
    public function show()
    {
        if ($this->cart->isEmpty()) {
            redirect(route('shop.cart'));
        }
        return $this->view('shop.checkout', [
            'lines'    => $this->cart->lines(),
            'total'    => $this->cart->total(),
            'customer' => Auth::guard('customer')->user(),
        ]);
    }

    /** POST /shop/checkout */
    public function place(Request $request)
    {
        $data = $request->validate([
            'shipping_address' => 'required|string|min:8|max:255',
            'phone'            => 'nullable|string|max:30',
        ]);
        $customer = Auth::guard('customer')->user();
        $cart = $this->cart->raw();

        if (!$cart) {
            redirect(route('shop.cart'));
        }

        // One transaction: lock the product rows so two shoppers cannot buy the same last item
        $result = DB::transaction(function () use ($cart, $customer, $data) {
            $products = DB::table('products')->whereIn('id', array_keys($cart))->where('status', 'active')->lockForUpdate()->get();
            $problems = [];
            $total = 0;

            foreach ($products as $p) {
                if ($p->stock < $cart[$p->id]) {
                    $problems[] = __('Only :count of :name left.', ['count' => $p->stock, 'name' => $p->name]);
                }
                $total += $p->price * $cart[$p->id];
            }
            if (count($products) !== count($cart)) {
                $problems[] = __('Some items are no longer available.');
            }
            if ($problems) {
                return ['ok' => false, 'problems' => $problems];
            }

            $orderId = DB::table('orders')->insertGetId([
                'user_id'          => $customer->id,
                'order_number'     => 'TMP-' . bin2hex(random_bytes(6)),
                'status'           => 'pending',
                'total'            => round($total, 2),
                'shipping_address' => $data['shipping_address'],
            ]);
            DB::table('orders')->where('id', $orderId)->update(['order_number' => 'ORD-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT)]);

            foreach ($products as $p) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId, 'product_id' => $p->id, 'product_name' => $p->name,
                    'quantity' => $cart[$p->id], 'unit_price' => $p->price,
                ]);
                DB::table('products')->where('id', $p->id)->decrement('stock', $cart[$p->id]);
            }
            if (!empty($data['phone'])) {
                DB::table('users')->where('id', $customer->id)->update(['phone' => $data['phone']]);
            }
            return ['ok' => true, 'order_id' => $orderId];
        });

        if (!$result['ok']) {
            flash('error', implode(' ', $result['problems']));
            redirect(route('shop.cart'));
        }

        $this->cart->clear();
        $order = Order::findOrFail($result['order_id']);
        Event::dispatch('order.placed', $order);

        flash('success', __('Thank you! Your order :number has been placed.', ['number' => $order->order_number]));
        redirect(route('shop.orders.show', ['order' => $order->id]));
    }
}
