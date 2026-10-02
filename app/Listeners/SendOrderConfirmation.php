<?php
namespace App\Listeners;

use App\Models\Order;
use System\Mail\Mail;

/**
 * Registered in app/bootstrap.php for the "order.placed" event.
 */
class SendOrderConfirmation
{
    public function handle(Order $order): void
    {
        $customer = $order->user;
        if (!$customer) {
            return;
        }

        Mail::to($customer->email, $customer->name)
            ->subject(__('Order :number confirmed', ['number' => $order->order_number]))
            ->view('emails.order-confirmation', ['order' => $order, 'items' => $order->items, 'customer' => $customer])
            ->queue();   // the customer does not wait for the mail server
    }
}
