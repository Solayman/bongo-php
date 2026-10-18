<?php

namespace Tests\Unit;

use System\Testing\TestCase;
use System\Validation\Validator;

class ValidatorTest extends TestCase
{
    public function testValidDataHasNoErrors(): void
    {
        $errors = Validator::make(
            ['name' => 'Mug', 'price' => '9.50', 'stock' => '3', 'status' => 'active', 'email' => 'a@b.co'],
            ['name' => 'required|max:10', 'price' => 'required|numeric|min:0', 'stock' => 'integer', 'status' => 'in:active,draft', 'email' => 'email']
        );
        $this->assertSame([], $errors);
    }

    public function testRequired(): void
    {
        $errors = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertSame('The name field is required.', $errors['name']);
    }

    public function testNullableSkipsOtherRules(): void
    {
        $this->assertSame([], Validator::make(['phone' => ''], ['phone' => 'nullable|max:3']));
    }

    public function testStringLengthAndNumericRange(): void
    {
        $errors = Validator::make(['name' => 'abcdef', 'price' => '-1'], ['name' => 'max:5', 'price' => 'numeric|min:0']);
        $this->assertContains('may not be longer than 5', $errors['name']);
        $this->assertContains('must be at least 0', $errors['price']);
    }

    public function testEmailIntegerIn(): void
    {
        $errors = Validator::make(
            ['email' => 'nope', 'qty' => '1.5', 'status' => 'gone'],
            ['email' => 'email', 'qty' => 'integer', 'status' => 'in:active,draft']
        );
        $this->assertCount(3, $errors);
    }

    public function testConfirmed(): void
    {
        $errors = Validator::make(['password' => 'secret123', 'password_confirmation' => 'other'], ['password' => 'confirmed']);
        $this->assertArrayHasKey('password', $errors);
        $this->assertSame([], Validator::make(['password' => 'a', 'password_confirmation' => 'a'], ['password' => 'confirmed']));
    }

    public function testUniqueAndExistsUseTheDatabase(): void
    {
        $this->assertArrayHasKey('email', Validator::make(['email' => 'admin@example.com'], ['email' => 'unique:users,email']));
        $this->assertSame([], Validator::make(['email' => 'new@example.com'], ['email' => 'unique:users,email']));
        // ignore id = the admin's own row
        $this->assertSame([], Validator::make(['email' => 'admin@example.com'], ['email' => 'unique:users,email,1']));
        $this->assertArrayHasKey('user_id', Validator::make(['user_id' => 999999], ['user_id' => 'exists:users,id']));
        $this->assertSame([], Validator::make(['user_id' => 1], ['user_id' => 'exists:users,id']));
    }

    public function testUnknownRuleThrows(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn() => Validator::make(['a' => 'x'], ['a' => 'banana']));
    }

    public function testIpAndMacRules(): void
    {
        $valid = [
            'ip4'  => '192.168.1.1',
            'ip6'  => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'mac1' => '00:1A:2B:3C:4D:5E',
            'mac2' => '00-1A-2B-3C-4D-5E',
        ];
        $errors = Validator::make($valid, [
            'ip4'  => 'ip|ipv4',
            'ip6'  => 'ip|ipv6',
            'mac1' => 'mac',
            'mac2' => 'mac_address',
        ]);
        $this->assertSame([], $errors);

        $invalid = [
            'ip4'  => '999.999.999.999',
            'ip6'  => '192.168.1.1',
            'mac1' => 'not-a-mac',
            'mac2' => '00:1A:2B:3C:4D',
        ];
        $bad = Validator::make($invalid, [
            'ip4'  => 'ipv4',
            'ip6'  => 'ipv6',
            'mac1' => 'mac',
            'mac2' => 'mac_address',
        ]);
        $this->assertCount(4, $bad);
        $this->assertSame('The ip4 must be a valid IPv4 address.', $bad['ip4']);
        $this->assertSame('The ip6 must be a valid IPv6 address.', $bad['ip6']);
        $this->assertSame('The mac1 must be a valid MAC address.', $bad['mac1']);
        $this->assertSame('The mac2 must be a valid MAC address.', $bad['mac2']);
    }

    public function testOmittedNullableFields(): void
    {
        $v = new Validator([], [
            'ip_address'    => 'nullable|ip',
            'secondary_mac' => 'nullable|mac',
            'name'          => 'required|string',
        ]);
        $errors = $v->validate();
        $this->assertSame(['name' => 'The name field is required.'], $errors);

        $validated = $v->validated();
        $this->assertNull($validated['ip_address']);
        $this->assertNull($validated['secondary_mac']);
    }
}
