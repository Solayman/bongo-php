<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class StorefrontTest extends HttpTestCase
{
    protected function registerCustomer(string $email): void
    {
        $this->submit('/shop/register', '/shop/register', [
            'name' => 'Shopper', 'email' => $email, 'password' => 'shopper-pass', 'password_confirmation' => 'shopper-pass',
        ])->assertRedirect('/shop');
        $this->get($this->verificationLink($email))->assertRedirect('/shop');
    }

    /** The link in the newest verification email sent to $email, as a path */
    protected function verificationLink(string $email): string
    {
        foreach (array_reverse($this->sentMails()) as $mail) {
            if (in_array($email, array_column($mail['to'] ?? [], 'email'), true) || str_contains(json_encode($mail['to'] ?? []), $email)) {
                preg_match('#href="([^"]+/shop/email/verify/[^"]+)"#', $mail['html'] ?? '', $m);
                $url = html_entity_decode($m[1] ?? '');
                return (string) parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
            }
        }
        throw new \System\Testing\AssertionFailed("No verification email for $email");
    }

    public function testNewCustomersVerifyTheirEmailBeforeCheckout(): void
    {
        $this->submit('/shop/register', '/shop/register', [
            'name' => 'New One', 'email' => 'new-one@example.com', 'password' => 'shopper-pass', 'password_confirmation' => 'shopper-pass',
        ])->assertRedirect('/shop');

        // Not verified yet: checkout and orders send them to the notice page
        $this->get('/shop/checkout')->assertRedirect('/shop/email/verify');
        $this->get('/shop/orders')->assertRedirect('/shop/email/verify');
        $this->get('/shop/email/verify')->assertSee('new-one@example.com')->assertSee('Send the link again');

        $link = $this->verificationLink('new-one@example.com');

        // A changed or expired link does nothing
        $this->get(str_replace('signature=', 'signature=0', $link))->assertStatus(403);
        $this->get(preg_replace('/expires=\d+/', 'expires=' . (time() + 99999), $link))->assertStatus(403);

        $this->get($link)->assertRedirect('/shop');
        $this->assertTrue($this->db('users')->where('email', 'new-one@example.com')->first()->email_verified_at !== null);
        $this->get('/shop/orders')->assertStatus(200);
    }

    protected function addToCart(int $productId, int $quantity = 1)
    {
        return $this->submit('/shop', '/shop/cart', ['product_id' => $productId, 'quantity' => $quantity]);
    }

    public function testCatalogIsPublicAndFiltersWork(): void
    {
        $this->get('/shop')->assertStatus(200)->assertSee('All products')->assertSee('Bluetooth Speaker')->assertSee('Page 1 of 2');
        $this->get('/shop?category=books')->assertSee('PHP from Scratch')->assertDontSee('Wireless Earbuds');
        $this->get('/shop?q=Yoga')->assertSee('Yoga Mat')->assertDontSee('Cricket Bat');
        $this->get('/shop/products/1')->assertStatus(200)->assertSee('Add to cart')->assertSee('You may also like');
        $this->get('/shop/products/999999')->assertStatus(404);
    }

    public function testDraftProductsAreHidden(): void
    {
        $draft = $this->db('products')->where('status', 'draft')->first();
        $this->get('/shop?q=' . urlencode($draft->name))->assertDontSee('>' . $draft->name . '<');
        $this->get('/shop/products/' . $draft->id)->assertStatus(404);
    }

    public function testCartAddUpdateRemove(): void
    {
        $this->addToCart(1, 2)->assertRedirect('/shop');
        $this->get('/shop/cart')->assertSee('Wireless Earbuds')->assertSee(money(2 * 39.99));
        $this->get('/shop')->assertSee('badge rounded-pill text-bg-warning ms-1">2<');

        $this->submit('/shop/cart', '/shop/cart/1', ['_method' => 'PATCH', 'quantity' => 3])->assertRedirect('/shop/cart');
        $this->get('/shop/cart')->assertSee(money(3 * 39.99));

        $this->submit('/shop/cart', '/shop/cart/1', ['_method' => 'DELETE'])->assertRedirect('/shop/cart');
        $this->get('/shop/cart')->assertSee('Your cart is empty.');
    }

    public function testCartNeverExceedsStockAndRejectsBadInput(): void
    {
        $id = $this->db('products')->insertGetId(['name' => 'Last One', 'sku' => 'SHOP-LAST', 'price' => 5, 'stock' => 1, 'status' => 'active']);
        $this->addToCart($id, 5);
        $this->get('/shop/cart')->assertSee('value="1" min="0" max="1"');

        $this->db('products')->where('id', $id)->update(['stock' => 0]);
        $this->cookies = [];
        $this->addToCart($id);
        $this->get('/shop')->assertSee('Last One is out of stock.');

        $this->addToCart(999999)->assertRedirect('/shop');
        $this->get('/shop/cart')->assertSee('Your cart is empty.');
    }

    public function testCheckoutRequiresACustomerAccount(): void
    {
        $this->addToCart(2);
        $this->get('/shop/checkout')->assertRedirect('/shop/login');

        // An admin session is a different guard: it does not count as a customer
        $this->loginAsAdmin();
        $this->get('/shop/checkout')->assertRedirect('/shop/login');
        $this->get('/')->assertStatus(200);
    }

    public function testRegistrationValidationAndRoleCannotBeChosen(): void
    {
        $this->submit('/shop/register', '/shop/register', ['name' => '', 'email' => 'admin@example.com', 'password' => 'short', 'password_confirmation' => 'x']);
        $this->get('/shop/register')->assertSee('The name field is required.')->assertSee('The email has already been taken.')->assertSee('The password must be at least 8 characters.');

        $this->submit('/shop/register', '/shop/register', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'sneaky-pass', 'password_confirmation' => 'sneaky-pass', 'role' => 'admin',
        ]);
        $this->assertSame('customer', $this->db('users')->where('email', 'sneaky@example.com')->value('role'));

        // ...and a customer cannot use the admin panel
        $this->cookies = [];
        $this->submit('/login', '/login', ['email' => 'sneaky@example.com', 'password' => 'sneaky-pass']);
        $this->get('/')->assertRedirect('/login');
    }

    public function testFullPurchaseDecrementsStockAndSendsConfirmation(): void
    {
        $this->registerCustomer('buyer@example.com');
        $stockBefore = (int) $this->db('products')->where('id', 3)->value('stock');
        $ordersBefore = $this->db('orders')->count();

        $this->addToCart(3, 2);
        $this->addToCart(4, 1);
        $this->get('/shop/checkout')->assertStatus(200)->assertSee('Place order');

        $this->submit('/shop/checkout', '/shop/checkout', ['shipping_address' => '']);
        $this->get('/shop/checkout')->assertSee('The shipping address field is required.');

        $response = $this->submit('/shop/checkout', '/shop/checkout', ['shipping_address' => '12 Lake Road, Dhaka', 'phone' => '+880 1711 000000']);
        $order = $this->db('orders')->orderBy('id', 'DESC')->first();
        $response->assertRedirect('/shop/orders/' . $order->id);

        $this->assertSame($ordersBefore + 1, $this->db('orders')->count());
        $this->assertSame('ORD-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), $order->order_number);
        $this->assertSame('pending', $order->status);
        $this->assertSame($stockBefore - 2, (int) $this->db('products')->where('id', 3)->value('stock'));
        $this->assertCount(2, $this->db('order_items')->where('order_id', $order->id)->get());

        $price3 = (float) $this->db('products')->where('id', 3)->value('price');
        $price4 = (float) $this->db('products')->where('id', 4)->value('price');
        $this->assertEquals(round(2 * $price3 + $price4, 2), (float) $order->total, 'the total uses database prices');

        $this->get('/shop/orders/' . $order->id)->assertSee($order->order_number)->assertSee('Thank you! Your order');
        $this->get('/shop/orders')->assertSee($order->order_number);
        $this->get('/shop/cart')->assertSee('Your cart is empty.');

        // Confirmation email through the order.placed event
        $mail = $this->lastMail();
        $this->assertSame('buyer@example.com', $mail['to'][0][0]);
        $this->assertSame("Order {$order->order_number} confirmed", $mail['subject']);

        // The admin sees the new order
        $this->cookies = [];
        $this->loginAsAdmin();
        $this->get('/orders')->assertSee($order->order_number);
    }

    public function testCustomersCannotSeeOtherCustomersOrders(): void
    {
        $this->registerCustomer('nosy@example.com');
        $someoneElses = $this->db('orders')->where('user_id', '!=', $this->db('users')->where('email', 'nosy@example.com')->value('id'))->first();
        $this->get('/shop/orders/' . $someoneElses->id)->assertStatus(404);
    }

    public function testStockIsRecheckedAtCheckout(): void
    {
        $id = $this->db('products')->insertGetId(['name' => 'Hot Item', 'sku' => 'SHOP-HOT', 'price' => 10, 'stock' => 2, 'status' => 'active']);
        $this->registerCustomer('late@example.com');
        $this->addToCart($id, 2);

        // Someone else bought one meanwhile
        $this->db('products')->where('id', $id)->update(['stock' => 1]);

        $before = $this->db('orders')->count();
        $this->submit('/shop/checkout', '/shop/checkout', ['shipping_address' => '1 Test Street'])->assertRedirect('/shop/cart');
        $this->get('/shop/cart')->assertSee('Only 1 of Hot Item left.');
        $this->assertSame($before, $this->db('orders')->count(), 'no order was created');
        $this->assertSame(1, (int) $this->db('products')->where('id', $id)->value('stock'), 'stock unchanged');
    }

    public function testCustomerLoginLogoutAndLockout(): void
    {
        $this->get('/shop/login')->assertSee('Sign in to your account');
        $this->submit('/shop/login', '/shop/login', ['email' => 'rahim1@example.com', 'password' => 'password'])->assertRedirect('/shop');
        $this->get('/shop')->assertSee('Rahim Uddin');
        $this->get('/shop/login')->assertRedirect('/shop');
        $this->submit('/shop', '/shop/logout')->assertRedirect('/shop');
        $this->get('/shop/orders')->assertRedirect('/shop/login');

        // Admins are not customers
        $this->submit('/shop/login', '/shop/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/shop/login');
        $this->get('/shop/login')->assertSee('These credentials do not match our records.');
    }
}
