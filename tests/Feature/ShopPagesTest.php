<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class ShopPagesTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function testEveryAdminPageLoads(): void
    {
        foreach (['/', '/dashboard', '/orders', '/orders/1', '/products', '/products/create', '/products/1/edit',
                  '/categories', '/categories/1/edit', '/customers', '/customers/2', '/profile', '/system'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, $response->status, "GET $path returned {$response->status}");
            $this->assertNotContains('Warning:', $response->body, "PHP warning on $path");
            $this->assertNotContains('Deprecated:', $response->body, "PHP deprecation on $path");
        }
    }

    public function testDashboardShowsStoreTotals(): void
    {
        $revenue = $this->db('orders')->where('status', '!=', 'cancelled')->sum('total');
        $this->get('/')->assertSee(money($revenue))->assertSee('Recent orders')->assertSee('Top products');
    }

    public function testUnknownPageIs404(): void
    {
        $this->get('/does-not-exist')->assertStatus(404)->assertSee('Page Not Found');
    }

    public function testCategoryLifecycle(): void
    {
        $this->submit('/categories', '/categories', ['name' => 'Toys & Games'])->assertRedirect('/categories');
        $category = $this->db('categories')->where('name', 'Toys & Games')->first();
        $this->assertSame('toys-games', $category->slug);

        // Same name again gets a unique slug
        $this->submit('/categories', '/categories', ['name' => 'Toys & Games']);
        $this->assertSame(1, $this->db('categories')->where('slug', 'toys-games-2')->count());

        $this->submit("/categories/{$category->id}/edit", "/categories/{$category->id}", ['name' => 'Toys'])->assertRedirect('/categories');
        $this->assertSame('toys', $this->db('categories')->where('id', $category->id)->first()->slug);

        $this->submit('/categories', "/categories/{$category->id}/delete")->assertRedirect('/categories');
        $this->assertNull($this->db('categories')->where('id', $category->id)->first());
    }

    public function testDeletingCategoryKeepsItsProducts(): void
    {
        $before = $this->db('products')->count();
        $id = $this->db('categories')->insertGetId(['name' => 'Temp', 'slug' => 'temp-pages']);
        $this->db('products')->insert(['category_id' => $id, 'name' => 'Orphan', 'sku' => 'ORPH-1', 'price' => 1, 'stock' => 1]);

        $this->submit('/categories', "/categories/$id/delete");
        $this->assertSame($before + 1, $this->db('products')->count());
        $this->assertNull($this->db('products')->where('sku', 'ORPH-1')->first()->category_id);
    }

    public function testOrderStatusUpdate(): void
    {
        $this->submit('/orders/1', '/orders/1/status', ['status' => 'shipped'])->assertRedirect('/orders/1');
        $this->assertSame('shipped', $this->db('orders')->where('id', 1)->first()->status);
        $this->get('/orders/1')->assertSee('marked as shipped');

        $this->submit('/orders/1', '/orders/1/status', ['status' => 'teleported']);
        $this->assertSame('shipped', $this->db('orders')->where('id', 1)->first()->status);
    }

    public function testOrderFilters(): void
    {
        $pending = $this->db('orders')->where('status', 'pending')->count();
        $page = $this->get('/orders?status=pending');
        $this->assertSame($pending, substr_count($page->body, 'text-bg-warning">Pending'));
    }

    public function testProfileAndPasswordChange(): void
    {
        $this->submit('/profile', '/profile', ['name' => 'Chief Admin', 'email' => 'admin@example.com', 'phone' => '123'])->assertRedirect('/profile');
        $this->get('/profile')->assertSee('Profile updated.')->assertSee('Chief Admin');

        // Email must stay unique
        $this->submit('/profile', '/profile', ['name' => 'Chief Admin', 'email' => 'rahim1@example.com']);
        $this->get('/profile')->assertSee('The email has already been taken.');

        // Wrong current password
        $this->submit('/profile', '/profile/password', ['current_password' => 'nope', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1']);
        $this->get('/profile')->assertSee('The current password is incorrect.');

        // Change, log out, log back in with the new password, then restore it
        $this->submit('/profile', '/profile/password', ['current_password' => 'password', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1'])
            ->assertRedirect('/profile');
        $this->submit('/', '/logout');
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'newpassword1'])->assertRedirect('/');
        $this->submit('/profile', '/profile/password', ['current_password' => 'newpassword1', 'password' => 'password', 'password_confirmation' => 'password']);
        $this->db('users')->where('id', 1)->update(['name' => 'Admin', 'phone' => null]);
    }
}
