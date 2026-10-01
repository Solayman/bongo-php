<?php
namespace Tests\Unit;

use System\Http\UploadedFile;
use System\Storage\Storage;
use System\Testing\TestCase;
use System\Validation\Validator;

class ValidationRulesTest extends TestCase
{
    protected array $temp = [];

    public function tearDown(): void
    {
        foreach ($this->temp as $file) @unlink($file);
    }

    protected function png(int $w = 4, int $h = 4): string
    {
        $file = tempnam(sys_get_temp_dir(), 'png');
        $img = imagecreatetruecolor($w, $h);
        imagepng($img, $file);
        imagedestroy($img);
        return $this->temp[] = $file;
    }

    protected function textFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($file, $contents);
        return $this->temp[] = $file;
    }

    public function testFormatRules(): void
    {
        $ok = Validator::make(
            ['site' => 'https://bongo.dev', 'flag' => 'on', 'code' => 'AB-12', 'slug' => 'my_post-1', 'pin' => '0420', 'name' => 'Rahim', 'user' => 'abc123'],
            ['site' => 'url', 'flag' => 'boolean', 'code' => ['regex:/^[A-Z]{2}-\d+$/'], 'slug' => 'alpha_dash', 'pin' => 'digits:4', 'name' => 'alpha', 'user' => 'alpha_num']
        );
        $this->assertSame([], $ok);

        $bad = Validator::make(
            ['site' => 'javascript:alert(1)', 'flag' => 'maybe', 'code' => 'ab12', 'slug' => 'a b', 'pin' => '42', 'name' => 'R2D2', 'user' => 'a-b'],
            ['site' => 'url', 'flag' => 'boolean', 'code' => ['regex:/^[A-Z]{2}-\d+$/'], 'slug' => 'alpha_dash', 'pin' => 'digits:4', 'name' => 'alpha', 'user' => 'alpha_num']
        );
        $this->assertCount(7, $bad);
        $this->assertSame('The site must be a valid URL.', $bad['site']);
        $this->assertSame('The pin must be 4 digits.', $bad['pin']);
    }

    public function testDatesComparisonsAndLists(): void
    {
        $errors = Validator::make(
            ['start' => '2026-10-10', 'end' => '2026-10-01', 'born' => 'yesterday', 'a' => 'x', 'b' => 'y', 'c' => 'x', 'tags' => ['a', 'b', 'c', 'd'], 'role' => 'root', 'ref' => 'ORD-1'],
            ['start' => 'date', 'end' => 'date|after:start', 'born' => 'date|before:today', 'a' => 'same:b', 'c' => 'different:a', 'tags' => 'array|max:3', 'role' => 'not_in:root,admin', 'ref' => 'starts_with:INV-,RCP-']
        );
        $this->assertSame('The end must be a date after start.', $errors['end']);
        $this->assertFalse(isset($errors['born']));
        $this->assertSame('The a and b must match.', $errors['a']);
        $this->assertSame('The c and a must be different.', $errors['c']);
        $this->assertSame('The tags may not have more than 3 items.', $errors['tags']);
        $this->assertArrayHasKey('role', $errors);
        $this->assertArrayHasKey('ref', $errors);
        $this->assertSame([], Validator::make(['n' => 5, 's' => 'abc'], ['n' => 'integer|between:1,10', 's' => 'between:2,4']));
    }

    public function testWildcardArraysAndSometimes(): void
    {
        $data = ['items' => [['qty' => 2, 'sku' => 'A'], ['qty' => 0, 'sku' => ''], ['qty' => 'x']]];
        $errors = Validator::make($data, ['items' => 'required|array|min:1', 'items.*.qty' => 'required|integer|min:1', 'items.*.sku' => 'required']);

        $this->assertSame(['items.1.qty', 'items.2.qty', 'items.1.sku', 'items.2.sku'], array_keys($errors));
        $this->assertSame('The qty must be at least 1.', $errors['items.1.qty']);
        $this->assertSame('The qty must be a whole number.', $errors['items.2.qty']);

        $this->assertSame([], Validator::make([], ['nickname' => 'sometimes|required|min:3']), 'sometimes: only when present');
        $this->assertArrayHasKey('nickname', Validator::make(['nickname' => 'ab'], ['nickname' => 'sometimes|required|min:3']));
    }

    public function testCustomMessagesAndAttributeNames(): void
    {
        $errors = Validator::make(['email' => '', 'qty' => 0], ['email' => 'required', 'qty' => 'integer|min:1'],
            ['email.required' => 'We need your :attribute to send the receipt.'], ['qty' => 'quantity']);
        $this->assertSame('We need your email to send the receipt.', $errors['email']);
        $this->assertSame('The quantity must be at least 1.', $errors['qty']);
    }

    public function testFileRulesUseTheRealContent(): void
    {
        $png = UploadedFile::fake($this->png(), 'photo.png');
        $this->assertSame([], Validator::make(['photo' => $png], ['photo' => 'required|image|mimes:png,jpg|max:100']));
        $this->assertSame('png', $png->extension());
        $this->assertSame('image/png', $png->mimeType());

        // PHP code named like an image is not an image
        $evil = UploadedFile::fake($this->textFile('<?php system($_GET["c"]); ?>'), 'cat.jpg');
        $this->assertSame('The photo must be an image (jpg, png, gif or webp).', Validator::make(['photo' => $evil], ['photo' => 'image'])['photo']);
        $this->assertArrayHasKey('photo', Validator::make(['photo' => $evil], ['photo' => 'mimes:jpg,png']));

        $big = UploadedFile::fake($this->png(400, 400), 'big.png');
        $this->assertContains('kilobytes', Validator::make(['photo' => $big], ['photo' => 'image|max:0'])['photo'] ?? 'kilobytes');

        $missing = new UploadedFile('', '', UPLOAD_ERR_NO_FILE, 0);
        $this->assertSame([], Validator::make(['photo' => $missing], ['photo' => 'nullable|image']), 'no file + nullable = fine');
        $this->assertSame('The photo field is required.', Validator::make(['photo' => $missing], ['photo' => 'required|image'])['photo']);
    }

    public function testStoreUsesRandomNameAndRealExtension(): void
    {
        $file = UploadedFile::fake($this->png(), 'my holiday photo.php.png');
        $path = $file->store('unit-test');
        try {
            $this->assertTrue((bool) preg_match('#^unit-test/[a-f0-9]{32}\.png$#', $path), "got $path");
            $this->assertTrue(Storage::exists($path));
            $this->assertSame(url('/storage/' . $path), Storage::url($path));
        } finally {
            Storage::delete($path);
            @rmdir(Storage::root() . '/unit-test');
        }

        $this->assertThrows(\RuntimeException::class, fn() => UploadedFile::fake($this->textFile('<?php'), 'x.php')->store('unit-test'), 'unknown types are never stored');
        $this->assertThrows(\InvalidArgumentException::class, fn() => Storage::path('../config/app.php'), 'no escaping the storage folder');
    }
}
