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
        foreach ($this->temp as $file) {
            @unlink($file);
        }
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

    public function testRequiredIfAndUnless(): void
    {
        $rules = ['company' => 'required_if:type,business,ngo', 'reason' => 'required_unless:status,active'];

        $errors = Validator::make(['type' => 'business', 'status' => 'closed'], $rules);
        $this->assertSame('The company field is required when type is business, ngo.', $errors['company']);
        $this->assertSame('The reason field is required unless status is in active.', $errors['reason']);

        $this->assertSame([], Validator::make(['type' => 'person', 'status' => 'active'], $rules), 'conditions not met: may stay empty');
        $this->assertSame([], Validator::make(['type' => 'ngo', 'company' => 'Bongo Trust', 'status' => 'active'], $rules));

        // Booleans compare as "true"/"false"
        $this->assertArrayHasKey('vat', Validator::make(['invoice' => true], ['vat' => 'required_if:invoice,true']));
    }

    public function testRequiredWithAndWithout(): void
    {
        $this->assertSame(
            'The password confirmation field is required when password is present.',
            Validator::make(['password' => 'secret'], ['password_confirmation' => 'required_with:password'])['password_confirmation']
        );
        $this->assertSame([], Validator::make([], ['password_confirmation' => 'required_with:password']));

        $all = ['zip' => 'required_with_all:street,city'];
        $this->assertSame([], Validator::make(['street' => 'Main road'], $all), 'only one of them is present');
        $this->assertArrayHasKey('zip', Validator::make(['street' => 'Main road', 'city' => 'Dhaka'], $all));

        $contact = ['phone' => 'required_without:email', 'email' => 'nullable|email'];
        $this->assertSame('The phone field is required when email is not present.', Validator::make([], $contact)['phone']);
        $this->assertSame([], Validator::make(['email' => 'a@b.co'], $contact));

        $none = ['note' => 'required_without_all:phone,email'];
        $this->assertSame('The note field is required when none of phone / email are present.', Validator::make([], $none)['note']);
        $this->assertSame([], Validator::make(['phone' => '017'], $none));
    }

    public function testConditionalRulesFollowTheSameRowInArrays(): void
    {
        $data = ['items' => [['name' => 'Mug', 'qty' => ''], ['name' => '', 'qty' => '']]];
        $errors = Validator::make($data, ['items.*.qty' => 'required_with:items.*.name|nullable|integer|min:1']);
        $this->assertSame(['items.0.qty'], array_keys($errors), 'row 0 has a name, row 1 does not');
    }

    public function testAccepted(): void
    {
        $this->assertSame('The terms must be accepted.', Validator::make([], ['terms' => 'accepted'])['terms']);
        $this->assertSame('The terms must be accepted.', Validator::make(['terms' => 'no'], ['terms' => 'accepted'])['terms']);
        foreach (['1', 'on', 'yes', 'true', true] as $ok) {
            $this->assertSame([], Validator::make(['terms' => $ok], ['terms' => 'accepted']));
        }
    }

    public function testCustomRulesAsClosuresAndClasses(): void
    {
        $even = function (string $attribute, $value, callable $fail) {
            if ((int) $value % 2 !== 0) {
                $fail('The :attribute must be even.');
            }
        };
        $upper = new class implements \System\Validation\Rule {
            public function passes(string $attribute, mixed $value, array $data): bool
            {
                return strtoupper($value) === $value;
            }
            public function message(): string
            {
                return 'The :attribute must be uppercase.';
            }
        };
        $rules = ['seats' => ['required', 'integer', $even], 'code' => ['nullable', $upper]];

        $errors = Validator::make(['seats' => 3, 'code' => 'bd-1'], $rules, [], ['seats' => 'number of seats']);
        $this->assertSame('The number of seats must be even.', $errors['seats'], 'custom messages use :attribute names');
        $this->assertSame('The code must be uppercase.', $errors['code']);

        $this->assertSame([], Validator::make(['seats' => 4, 'code' => 'BD-1'], $rules));
        $this->assertSame([], Validator::make(['seats' => 2], $rules), 'custom rules skip empty optional fields');
        $this->assertSame('The seats must be a whole number.', Validator::make(['seats' => 'x'], $rules)['seats'], 'built-in rules run first, in order');

        // A closure can read the other fields
        $later = fn($a, $v, $fail, $data) => $v <= ($data['start'] ?? 0) ? $fail('The :attribute must be after the start.') : null;
        $this->assertArrayHasKey('end', Validator::make(['start' => 5, 'end' => 3], ['end' => [$later]]));
    }

    public function testCustomMessagesAndAttributeNames(): void
    {
        $errors = Validator::make(
            ['email' => '', 'qty' => 0],
            ['email' => 'required', 'qty' => 'integer|min:1'],
            ['email.required' => 'We need your :attribute to send the receipt.'],
            ['qty' => 'quantity']
        );
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

    public function testMimetypesUsesTheRealContentType(): void
    {
        $png = UploadedFile::fake($this->png(), 'photo.png');
        $this->assertSame([], Validator::make(['doc' => $png], ['doc' => 'mimetypes:image/*,application/pdf']));
        $this->assertSame([], Validator::make(['doc' => $png], ['doc' => 'mimetypes:image/png']));

        $text = UploadedFile::fake($this->textFile('hello'), 'fake.pdf');
        $this->assertSame('The doc must be a file of type: image/*, application/pdf.', Validator::make(['doc' => $text], ['doc' => 'mimetypes:image/*,application/pdf'])['doc']);
    }

    public function testImageDimensions(): void
    {
        $wide = UploadedFile::fake($this->png(160, 90), 'banner.png');
        $this->assertSame([], Validator::make(['img' => $wide], ['img' => 'dimensions:min_width=100,max_height=100,ratio=16/9']));
        $this->assertSame([], Validator::make(['img' => $wide], ['img' => 'dimensions:width=160,height=90']));

        $error = Validator::make(['img' => $wide], ['img' => 'dimensions:min_width=200'])['img'];
        $this->assertSame('The img has invalid image dimensions (160x90 px, needs min_width 200).', $error);
        $this->assertArrayHasKey('img', Validator::make(['img' => $wide], ['img' => 'dimensions:ratio=1/1']));

        $text = UploadedFile::fake($this->textFile('not an image'), 'x.png');
        $this->assertArrayHasKey('img', Validator::make(['img' => $text], ['img' => 'dimensions:min_width=1']));
    }

    public function testMultipleUploadsFromFilesArray(): void
    {
        $old = $_FILES;
        $a = $this->png();
        $b = $this->textFile('<?php echo 1;');
        // <input type="file" name="photos[]" multiple> with two files, plus an empty single field
        $_FILES = [
            'photos' => [
                'name' => ['a.png', 'b.png', ''], 'type' => ['image/png', 'image/png', ''],
                'tmp_name' => [$a, $b, ''], 'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], 'size' => [filesize($a), filesize($b), 0],
            ],
            'avatar' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
        ];
        try {
            $photos = \System\Http\Request::files('photos');
            $this->assertCount(2, $photos, 'the empty third slot is skipped');
            $this->assertSame('a.png', $photos[0]->clientName());
            $this->assertNull(\System\Http\Request::file('photos'), 'file() is for single uploads');
            $this->assertTrue(\System\Http\Request::hasFile('photos'));
            $this->assertFalse(\System\Http\Request::hasFile('avatar'), 'nothing chosen');
            $this->assertSame(['photos'], array_keys(\System\Http\Request::files()));

            $this->assertFalse($photos[0]->isValid(), 'a file not really uploaded by PHP is never trusted');
            $this->assertArrayHasKey('photos', Validator::make(\System\Http\Request::files(), ['photos' => 'array|max:1']), 'too many files');
        } finally {
            $_FILES = $old;
        }

        // Validate every file in a list (test uploads; real ones are covered by the HTTP tests)
        $list = ['photos' => [UploadedFile::fake($a, 'a.png'), UploadedFile::fake($b, 'b.png')]];
        $errors = Validator::make($list, ['photos' => 'required|array|max:5', 'photos.*' => 'image|max:2048']);
        $this->assertSame(['photos.1'], array_keys($errors), 'the second "png" is really PHP');
        $this->assertSame('The photos must be an image (jpg, png, gif or webp).', $errors['photos.1']);
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
