<?php
namespace App\Http\Controllers;

use App\Models\Order;
use System\Database\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $since = date('Y-m-d 00:00:00', strtotime('-29 days'));

        $stats = [
            'revenue'   => DB::table('orders')->where('status', '!=', 'cancelled')->sum('total'),
            'revenue30' => DB::table('orders')->where('status', '!=', 'cancelled')->where('created_at', '>=', $since)->sum('total'),
            'orders'    => DB::table('orders')->count(),
            'pending'   => DB::table('orders')->where('status', 'pending')->count(),
            'customers' => DB::table('users')->where('role', 'customer')->count(),
            'products'  => DB::table('products')->count(),
            'lowStock'  => DB::table('products')->where('stock', '<', 10)->count(),
        ];

        $recentOrders = DB::table('orders')
            ->select('orders.*', 'users.name AS customer_name')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->orderBy('orders.created_at', 'DESC')
            ->limit(8)
            ->get();

        $statusCounts = [];
        foreach (Order::STATUSES as $status) {
            $statusCounts[$status] = DB::table('orders')->where('status', $status)->count();
        }

        $topProducts = DB::raw(
            'SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.quantity * oi.unit_price) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status != ?
             GROUP BY oi.product_name
             ORDER BY units DESC, revenue DESC
             LIMIT 5',
            ['cancelled']
        );

        $lowStock = DB::table('products')->where('stock', '<', 10)->orderBy('stock')->limit(5)->get();

        echo $this->view('dashboard.index', compact('stats', 'recentOrders', 'statusCounts', 'topProducts', 'lowStock'));
    }
}
