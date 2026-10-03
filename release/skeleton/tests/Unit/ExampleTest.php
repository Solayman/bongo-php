<?php
namespace Tests\Unit;

use System\Testing\TestCase;
use System\Validation\Validator;

class ExampleTest extends TestCase
{
    public function testValidation(): void
    {
        $this->assertSame([], Validator::make(['email' => 'a@b.co'], ['email' => 'required|email']));
    }
}
