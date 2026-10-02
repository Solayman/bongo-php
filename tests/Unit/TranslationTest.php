<?php
namespace Tests\Unit;

use System\Testing\TestCase;
use System\Translation\Lang;
use System\Translation\Translator;
use System\Validation\Validator;

class TranslationTest extends TestCase
{
    protected string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_lang_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/bn', 0777, true);
        mkdir($this->dir . '/en', 0777, true);
        file_put_contents($this->dir . '/bn.json', json_encode(['Hello, :name!' => 'হ্যালো, :name!', 'apple|apples' => 'আপেল|আপেলগুলো'], JSON_UNESCAPED_UNICODE));
        file_put_contents($this->dir . '/bn/shop.php', "<?php return ['cart' => ['empty' => 'ঝুড়ি খালি']];");
        file_put_contents($this->dir . '/en/shop.php', "<?php return ['cart' => ['empty' => 'Your cart is empty', 'only_en' => 'English only']];");
    }

    public function tearDown(): void
    {
        foreach (['bn.json', 'bn/shop.php', 'en/shop.php'] as $f) @unlink("$this->dir/$f");
        @rmdir("$this->dir/bn");
        @rmdir("$this->dir/en");
        @rmdir($this->dir);
        Lang::setTranslator(null);
    }

    public function testSentenceAndGroupKeysWithFallback(): void
    {
        $t = new Translator($this->dir, 'bn', 'en');
        $this->assertSame('হ্যালো, Rahim!', $t->get('Hello, :name!', ['name' => 'Rahim']));
        $this->assertSame('ঝুড়ি খালি', $t->get('shop.cart.empty'));
        $this->assertSame('English only', $t->get('shop.cart.only_en'), 'missing in bn → fallback locale');
        $this->assertSame('Not translated', $t->get('Not translated'), 'missing everywhere → the key');
        $this->assertSame('shop.cart.nope', $t->get('shop.cart.nope'));
        $this->assertTrue($t->has('shop.cart.empty'));
        $this->assertFalse($t->has('shop.cart.only_en'));

        $t->setLocale('en');
        $this->assertSame('Hello, Rahim!', $t->get('Hello, :name!', ['name' => 'Rahim']));
        $this->assertSame('Your cart is empty', $t->get('shop.cart.empty'));
    }

    public function testReplacementCaseVariants(): void
    {
        $t = new Translator($this->dir, 'en');
        $this->assertSame('rahim / Rahim / RAHIM', $t->get(':name / :Name / :NAME', ['name' => 'rahim']));
        $this->assertSame('5 / 55', $t->get(':n / :nn', ['n' => '5', 'nn' => '55']), 'longer keys are replaced first');
    }

    public function testPluralChoices(): void
    {
        $t = new Translator($this->dir, 'en');
        $line = '{0} No orders|{1} One order|[2,4] A few orders (:count)|[5,*] :count orders';
        $this->assertSame('No orders', $t->choice($line, 0));
        $this->assertSame('One order', $t->choice($line, 1));
        $this->assertSame('A few orders (3)', $t->choice($line, 3));
        $this->assertSame('12 orders', $t->choice($line, 12));
        $this->assertSame('apple', $t->choice('apple|apples', 1));
        $this->assertSame('apples', $t->choice('apple|apples', 2));

        $t->setLocale('bn');
        $this->assertSame('আপেলগুলো', $t->choice('apple|apples', 7));
    }

    public function testValidationMessagesFollowTheLocale(): void
    {
        Lang::setTranslator(new Translator(BASE_PATH . '/lang', 'bn', 'en'));
        $errors = Validator::make(['email' => '', 'name' => 'ab'], ['email' => 'required', 'name' => 'min:3']);
        $this->assertSame('ইমেইল দেওয়া আবশ্যক।', $errors['email']);
        $this->assertSame('নাম কমপক্ষে 3 অক্ষরের হতে হবে।', $errors['name']);

        Lang::setTranslator(new Translator(BASE_PATH . '/lang', 'en', 'en'));
        $this->assertSame('The email field is required.', Validator::make(['email' => ''], ['email' => 'required'])['email']);
    }

    public function testEveryBanglaValidationTemplateMatchesAnEnglishMessage(): void
    {
        // Each sentence key must be a real English message, or the translation is never used
        $bn = json_decode(file_get_contents(BASE_PATH . '/lang/bn.json'), true);
        $source = file_get_contents(BASE_PATH . '/system/Validation/Validator.php');
        foreach (array_keys($bn) as $key) {
            if (str_starts_with($key, 'The :attribute') || str_starts_with($key, 'The selected')) {
                $this->assertContains($key, $source, "bn.json key not used by the validator: $key");
            }
        }
    }
}
