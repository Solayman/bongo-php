<?php
namespace App\Http\Controllers;

use App\Models\Order;
use System\Database\DB;
use System\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('q');

        $query = DB::table('orders')
            ->select('orders.*', 'users.name AS customer_name', 'users.email AS customer_email')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->orderBy('orders.created_at', 'DESC');

        if (in_array($status, Order::STATUSES, true)) {
            $query->where('orders.status', $status);
        }
        if ($search) {
            $query->whereRaw('orders.order_number LIKE ? OR users.name LIKE ?', ["%$search%", "%$search%"]);
        }

        return $this->view('orders.index', [
            'orders'   => $query->paginate(15),
            'filters'  => ['status' => $status, 'q' => $search],
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show($id)
    {
        $order = DB::table('orders')
            ->select('orders.*', 'users.name AS customer_name', 'users.email AS customer_email', 'users.phone AS customer_phone')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.id', (int) $id)
            ->first() ?? abort(404, 'Order not found');

        return $this->view('orders.show', [
            'order'    => $order,
            'items'    => DB::table('order_items')->where('order_id', $order->id)->get(),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find((int) $id) ?? abort(404, 'Order not found');
        $data = $request->validate(['status' => 'required|in:' . implode(',', Order::STATUSES)]);

        Order::query()->where('id', $order->id)->update(['status' => $data['status']]);

        flash('success', "Order {$order->order_number} marked as {$data['status']}.");
        redirect(url("/orders/{$order->id}"));
    }
}
