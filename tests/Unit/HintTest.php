<?php
namespace Tests\Unit;

use System\Debug\Hint;
use System\Testing\TestCase;

class HintTest extends TestCase
{
    protected function errorFrom(string $code): \Throwable
    {
        try {
            eval($code);
        } catch (\Throwable $e) {
            return $e;
        }
        throw new \System\Testing\AssertionFailed("No error from: $code");
    }

    public function testForgottenDollarSign(): void
    {
        $e = $this->errorFrom('namespace App\Http\Controllers; return request->all();');
        $this->assertSame('Did you mean $request? Variables start with $: write $request instead of request.', Hint::for($e));
    }

    public function testMissingRequestParameter(): void
    {
        $e = new \ErrorException('Undefined variable $request');
        $this->assertContains('Add Request $request to the action', Hint::for($e));
    }

    public function testMissingUseStatement(): void
    {
        $e = $this->errorFrom('namespace App\Http\Controllers; return Category::all();');
        $this->assertSame('Add "use App\Models\Category;" at the top of the file.', Hint::for($e));
    }

    public function testMethodTypo(): void
    {
        $e = $this->errorFrom('return (new \System\Http\Request())->valdate([]);');
        $this->assertSame('Did you mean validate()?', Hint::for($e));
    }

    public function testNoHintForOtherErrors(): void
    {
        $this->assertSame(null, Hint::for(new \RuntimeException('Something else')));
    }
}
