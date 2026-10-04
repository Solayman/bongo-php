<?php

namespace App\Policies;

use App\Models\Product;
use System\Database\DB;

/**
 * Who may change products. Found automatically for App\Models\Product,
 * used by $this->authorize('delete', $product) and @can('delete', $product).
 */
class ProductPolicy
{
    public function update(object $user, Product $product): bool
    {
        return $user->role === 'admin';
    }

    /** A product that customers have ordered stays, so their order history keeps it: set it to draft instead */
    public function delete(object $user, Product $product): bool
    {
        return $user->role === 'admin'
            && !DB::table('order_items')->where('product_id', (int) $product->id)->exists();
    }
}
