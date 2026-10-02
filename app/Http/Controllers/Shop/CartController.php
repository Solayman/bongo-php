<?php
namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cart;

class CartController extends Controller
{
    public function __construct(protected Cart $cart) {}

    /** GET /shop/cart */
    public function show()
    {
        echo $this->view('shop.cart', ['lines' => $this->cart->lines(), 'total' => $this->cart->total()]);
    }

    /** POST /shop/cart  {product_id, quantity} */
    public function add()
    {
        $data = $this->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity'   => 'nullable|integer|between:1,' . Cart::MAX_QUANTITY,
        ]);
        $product = Product::findOrFail((int) $data['product_id']);

        if ($this->cart->add($product, (int) ($data['quantity'] ?? 1)) === 0) {
            flash('error', __(':name is out of stock.', ['name' => $product->name]));
        } else {
            flash('success', __(':name was added to your cart.', ['name' => $product->name]));
        }
        back();
    }

    /** PATCH /shop/cart/{product}  {quantity} */
    public function update(Product $product)
    {
        $data = $this->validate(['quantity' => 'required|integer|between:0,' . Cart::MAX_QUANTITY]);
        $this->cart->update($product->id, min((int) $data['quantity'], (int) $product->stock));
        redirect(route('shop.cart'));
    }

    /** DELETE /shop/cart/{product} */
    public function remove(Product $product)
    {
        $this->cart->remove($product->id);
        flash('success', __(':name was removed from your cart.', ['name' => $product->name]));
        redirect(route('shop.cart'));
    }
}
