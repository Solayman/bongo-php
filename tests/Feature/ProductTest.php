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

        $this->submit("/products/{$product->id}/edit", "/products/{$product->id}", ['_method' => 'PUT',
            'name' => 'Test Kettle XL', 'sku' => 'TST-1', 'category_id' => '1', 'price' => '24.00',
            'stock' => '3', 'status' => 'draft', 'description' => '',
        ])->assertRedirect('/products');

        $updated = $this->db('products')->where('id', $product->id)->first();
        $this->assertSame('Test Kettle XL', $updated->name);
        $this->assertSame('draft', $updated->status);
        $this->assertEquals(1, $updated->category_id);

        $this->submit('/products', "/products/{$product->id}", ['_method' => 'DELETE'])->assertRedirect('/products');
        $this->assertNull($this->db('products')->where('id', $product->id)->first());
    }

    public function testMissingProductIs404(): void
    {
        $this->get('/products/999999/edit')->assertStatus(404)->assertSee('Product not found');
    }

    public function testDeleteRequiresPost(): void
    {
        $this->get('/products/1')->assertStatus(405);
        $this->assertSame('BG-1000', $this->db('products')->where('id', 1)->first()->sku, 'GET must not delete');
    }

    protected function makePng(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'png') . '.png';
        $img = imagecreatetruecolor(20, 20);
        imagefill($img, 0, 0, imagecolorallocate($img, 220, 60, 60));
        imagepng($img, $file);
        imagedestroy($img);
        return $file;
    }

    public function testImageUploadReplaceRemoveAndDelete(): void
    {
        $png = $this->makePng();
        $this->submit('/products/create', '/products', [
            'name' => 'Photo Mug', 'sku' => 'IMG-1', 'price' => '9', 'stock' => '3', 'status' => 'active',
            'image' => new \CURLFile($png, 'image/png', 'mug.png'),
        ])->assertRedirect('/products');

        $product = $this->db('products')->where('sku', 'IMG-1')->first();
        $this->assertTrue((bool) preg_match('#^products/[a-f0-9]{32}\.png$#', (string) $product->image), 'stored with a random name');
        $file = BASE_PATH . '/public/storage/' . $product->image;
        $this->assertTrue(is_file($file));

        // The list shows the thumbnail, and the web server serves the file
        $this->get('/products?q=IMG-1')->assertSee('storage/' . $product->image);
        $image = $this->get('/storage/' . $product->image);
        $image->assertStatus(200);
        $this->assertSame('image/png', $image->header('Content-Type'));

        // Replace: the old file is deleted
        $this->submit("/products/{$product->id}/edit", "/products/{$product->id}", [
            '_method' => 'PUT', 'name' => 'Photo Mug', 'sku' => 'IMG-1', 'price' => '9', 'stock' => '3', 'status' => 'active',
            'image' => new \CURLFile($this->makePng(), 'image/png', 'new.png'),
        ])->assertRedirect('/products');
        $new = $this->db('products')->where('id', $product->id)->first()->image;
        $this->assertNotSame($product->image, $new);
        clearstatcache(); // the server deleted it; don't trust this process's cached stat
        $this->assertFalse(is_file($file), 'the replaced image is removed');

        // Edit without choosing a file: browsers still send an empty file part; the image must stay
        $token = $this->get("/products/{$product->id}/edit")->csrfToken();
        $boundary = 'bongo' . bin2hex(random_bytes(6));
        $body = '';
        foreach (['_token' => $token, '_method' => 'PUT', 'name' => 'Photo Mug 2', 'sku' => 'IMG-1', 'price' => '9', 'stock' => '3', 'status' => 'active'] as $k => $v) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
        }
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"image\"; filename=\"\"\r\nContent-Type: application/octet-stream\r\n\r\n\r\n--$boundary--\r\n";
        $this->request('POST', "/products/{$product->id}", $body, ['Content-Type' => "multipart/form-data; boundary=$boundary"])
            ->assertRedirect('/products');
        $row = $this->db('products')->where('id', $product->id)->first();
        $this->assertSame('Photo Mug 2', $row->name);
        $this->assertSame($new, $row->image, 'an empty file field keeps the current image');
        clearstatcache();
        $this->assertTrue(is_file(BASE_PATH . '/public/storage/' . $new));

        // Remove the image
        $this->submit("/products/{$product->id}/edit", "/products/{$product->id}", [
            '_method' => 'PUT', 'name' => 'Photo Mug', 'sku' => 'IMG-1', 'price' => '9', 'stock' => '3', 'status' => 'active', 'remove_image' => '1',
        ]);
        $this->assertNull($this->db('products')->where('id', $product->id)->first()->image);
        clearstatcache();
        $this->assertFalse(is_file(BASE_PATH . '/public/storage/' . $new));

        $this->submit('/products', "/products/{$product->id}", ['_method' => 'DELETE']);
    }

    public function testFakeImageIsRejected(): void
    {
        $evil = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($evil, '<?php echo "pwned"; ?>');

        $this->submit('/products/create', '/products', [
            'name' => 'Evil', 'sku' => 'EVIL-1', 'price' => '1', 'stock' => '1', 'status' => 'active',
            'image' => new \CURLFile($evil, 'image/jpeg', 'cat.jpg'),
        ])->assertRedirect('/products/create');

        $this->get('/products/create')->assertSee('The image must be an image');
        $this->assertNull($this->db('products')->where('sku', 'EVIL-1')->first());
        @unlink($evil);
    }
}
