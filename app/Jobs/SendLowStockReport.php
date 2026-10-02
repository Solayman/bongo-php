<?php
namespace App\Jobs;

use System\Database\DB;
use System\Mail\Mail;
use System\Queue\Job;

/**
 * Emails every admin the products that are running low.
 * Scheduled daily in app/schedule.php; can also be dispatched by hand: dispatch(new SendLowStockReport()).
 */
class SendLowStockReport extends Job
{
    public int $tries = 2;

    public function __construct(public int $threshold = 10) {}

    public function handle(): void
    {
        $products = DB::table('products')->where('stock', '<', $this->threshold)->where('status', 'active')->orderBy('stock')->get();
        if (!$products) {
            return;
        }

        $rows = '';
        foreach ($products as $p) {
            $rows .= '<tr><td>' . e($p->name) . '</td><td>' . e($p->sku) . '</td><td align="right">' . (int) $p->stock . '</td></tr>';
        }
        $html = '<h2>' . e(__('Low stock report')) . '</h2><table cellpadding="6" border="1" style="border-collapse:collapse">'
            . '<tr><th>' . e(__('Product')) . '</th><th>SKU</th><th>' . e(__('Stock')) . '</th></tr>' . $rows . '</table>';

        foreach (DB::table('users')->where('role', 'admin')->get() as $admin) {
            Mail::to($admin->email, $admin->name)->subject(__('Low stock: :count products', ['count' => count($products)]))->html($html)->send();
        }
    }
}
