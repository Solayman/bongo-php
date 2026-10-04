<?php

namespace App\Services;

use App\Models\Product;
use System\Http\Session;

/**
 * Shopping cart kept in the session: [product_id => quantity].
 * Prices always come from the database, never from the session.
 */
class Cart
{
    protected const KEY = 'cart';
    public const MAX_QUANTITY = 20;

    /** Add to the cart (never more than the stock); returns the new quantity, 0 if it cannot be added */
    public function add(Product $product, int $quantity = 1): int
    {
        $items = $this->raw();
        $new = min(self::MAX_QUANTITY, ($items[$product->id] ?? 0) + max(1, $quantity), max(0, (int) $product->stock));
        if ($new <= 0 || $product->status !== 'active') {
            return 0;
        }
        $items[$product->id] = $new;
        $this->save($items);
        return $new;
    }

    public function update(int $productId, int $quantity): void
    {
        $items = $this->raw();
        if ($quantity <= 0) {
            unset($items[$productId]);
        } elseif (isset($items[$productId])) {
            $items[$productId] = min(self::MAX_QUANTITY, $quantity);
        }
        $this->save($items);
    }

    public function remove(int $productId): void
    {
        $this->update($productId, 0);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /** [product_id => quantity] */
    public function raw(): array
    {
        return array_map('intval', (array) Session::get(self::KEY, []));
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /**
     * Lines with current product data: [['product' => Product, 'quantity' => n, 'subtotal' => x], ...]
     * Products that were deleted or unpublished are dropped.
     */
    public function lines(): array
    {
        $raw = $this->raw();
        if (!$raw) {
            return [];
        }
        $products = Product::query()->whereIn('id', array_keys($raw))->where('status', 'active')->get();
        $lines = [];
        foreach ($products as $product) {
            $quantity = $raw[$product->id];
            $lines[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => round($product->price * $quantity, 2)];
        }
        return $lines;
    }

    public function total(): float
    {
        return round(array_sum(array_column($this->lines(), 'subtotal')), 2);
    }

    protected function save(array $items): void
    {
        Session::put(self::KEY, $items);
    }
}
