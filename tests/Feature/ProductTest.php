<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class ProductTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function testListSearchAndPagination(): void
    {
        $this->get('/products')->assertStatus(200)->assertSee('BG-1019')->assertSee('Page 1 of 2');
        $this->get('/products?page=2')->assertStatus(200)->assertSee('BG-1000');
        $this->get('/products?q=Yoga')->assertSee('Yoga Mat')->assertDontSee('Cricket Bat');
    }

    public function testCreateValidatesInput(): void
    {
        $this->submit('/products/create', '/products', ['name' => '', 'sku' => 'BG-1000', 'price' => 'abc', 'stock' => '-1', 'status' => 'x'])
            ->assertRedirect('/products/create');

        $this->get('/products/create')
            ->assertSee('The name field is required.')
            ->assertSee('The sku has already been taken.')
            ->assertSee('The price must be a number.')
            ->assertSee('The stock must be at least 0.')
            ->assertSee('value="BG-1000"'); // old input is kept
    }

    public function testCreateEditDelete(): void
    {
        $this->submit('/products/create', '/products', [
            'name' => 'Test Kettle', 'sku' => 'TST-1', 'category_id' => '', 'price' => '19.99',
            'stock' => '7', 'status' => 'active', 'description' => '<b>hot</b>',
        ])->assertRedirect('/products');

        $product = $this->db('products')->where('sku', 'TST-1')->first();
        $this->assertSame('Test Kettle', $product->name);
        $this->assertNull($product->category_id);
        $this->get('/products')->assertSee('Product &quot;Test Kettle&quot; created.');

        $this->submit("/products/{$product->id}/edit", "/products/{$product->id}", [
            'name' => 'Test Kettle XL', 'sku' => 'TST-1', 'category_id' => '1', 'price' => '24.00',
            'stock' => '3', 'status' => 'draft', 'description' => '',
        ])->assertRedirect('/products');

        $updated = $this->db('products')->where('id', $product->id)->first();
        $this->assertSame('Test Kettle XL', $updated->name);
        $this->assertSame('draft', $updated->status);
        $this->assertEquals(1, $updated->category_id);

        $this->submit('/products', "/products/{$product->id}/delete")->assertRedirect('/products');
        $this->assertNull($this->db('products')->where('id', $product->id)->first());
    }

    public function testMissingProductIs404(): void
    {
        $this->get('/products/999999/edit')->assertStatus(404)->assertSee('Product not found');
    }

    public function testDeleteRequiresPost(): void
    {
        $this->get('/products/1/delete')->assertStatus(405);
        $this->assertSame('BG-1000', $this->db('products')->where('id', 1)->first()->sku, 'GET must not delete');
    }
}
