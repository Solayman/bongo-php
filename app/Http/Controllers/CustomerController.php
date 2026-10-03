<?php
namespace App\Http\Controllers;

use System\Database\DB;
use System\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');
        $where = "u.role = 'customer'";
        $bindings = [];
        if ($search) {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $bindings = ["%$search%", "%$search%"];
        }

        $customers = DB::raw(
            "SELECT u.id, u.name, u.email, u.phone, u.created_at,
                    COUNT(o.id) AS order_count,
                    COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total END), 0) AS total_spent,
                    MAX(o.created_at) AS last_order_at
             FROM users u
             LEFT JOIN orders o ON o.user_id = u.id
             WHERE $where
             GROUP BY u.id
             ORDER BY total_spent DESC",
            $bindings
        );

        return $this->view('customers.index', ['customers' => $customers, 'search' => $search]);
    }

    public function show($id)
    {
        $customer = DB::table('users')->where('id', (int) $id)->where('role', 'customer')->first()
            ?? abort(404, 'Customer not found');

        $orders = DB::table('orders')->where('user_id', $customer->id)->orderBy('created_at', 'DESC')->get();

        return $this->view('customers.show', [
            'customer' => $customer,
            'orders'   => $orders,
            'spent'    => DB::table('orders')->where('user_id', $customer->id)->where('status', '!=', 'cancelled')->sum('total'),
        ]);
    }
}
