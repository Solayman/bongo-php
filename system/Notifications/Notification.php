<?php

namespace System\Notifications;

/**
 * One message for a person, sent through one or more channels:
 *
 *   class OrderShipped extends Notification
 *   {
 *       public function __construct(public object $order) {}
 *       public function via(object $user): array { return ['mail', 'sms']; }
 *       public function toMail(object $user): \System\Mail\Message { return Mail::message()->subject('Shipped')->text('...'); }
 *       public function toSms(object $user): string { return "Order {$this->order->number} has shipped"; }
 *       public function toSlack(object $user): string { return "Order {$this->order->number} shipped"; }
 *   }
 *
 *   Notify::send($user, new OrderShipped($order));
 */
abstract class Notification
{
    /** Channels for this person: mail, sms, slack */
    abstract public function via(object $notifiable): array;
}
