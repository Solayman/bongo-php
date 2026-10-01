<?php

namespace App\Models;

use System\Database\Model;

class Order extends Model
{
    protected static $table = 'orders';

    public const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    /** Bootstrap badge colour for a status */
    public static function badge(string $status): string
    {
        return match ($status) {
            'pending'    => 'text-bg-warning',
            'processing' => 'text-bg-info',
            'shipped'    => 'text-bg-primary',
            'delivered'  => 'text-bg-success',
            'cancelled'  => 'text-bg-secondary',
            default      => 'text-bg-light',
        };
    }
}
